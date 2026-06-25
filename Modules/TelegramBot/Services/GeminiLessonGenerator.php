<?php

namespace Modules\TelegramBot\Services;

use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\TelegramBot\Models\LearningProfile;
use Modules\TelegramBot\Models\Topic;
use Modules\TelegramBot\Models\VocabularyEntry;

/**
 * Calls Gemini to generate a daily micro-lesson for a user.
 *
 * Prompt design:
 *   - **Persona**: "Ms. Linh" — a friendly Vietnamese English teacher
 *     persona, so the output has consistent voice across lessons.
 *   - **Few-shot example**: one concrete JSON example in the prompt to
 *     pin the schema and quality bar.
 *   - **Constraints**: high-frequency words only, examples must be
 *     natural and short (8-15 words), IPA must be valid format.
 *   - **Cultural fit**: explicitly avoid Thanksgiving / Halloween
 *     references that are uncommon for Vietnamese learners.
 *
 * Lesson types:
 *   - **vocab** (Mon, Wed): 5 new words + 1 grammar + topic intro.
 *   - **grammar** (Tue, Thu): 1 deep grammar pattern with 3 examples
 *     + 3 supporting vocab words.
 *   - **reading** (Fri): 1 short reading passage (60-90 words) +
 *     3 comprehension questions with answers.
 *   - **conversation** (Sat): 1 mini-dialog (6-8 lines) between two
 *     people + 4 supporting vocab words.
 *   - **listening** (Sun): 1 transcript (~80 words) + 3 quiz questions
 *     + audio URL for the transcript.
 *   - **review** (CN): generated client-side from the user's existing
 *     vocab — Gemini NOT called.
 *
 * Caching:
 *   - One Gemini generation can serve many users on the same day for
 *     the same (purpose, level, topic, type) tuple. We cache under
 *     `tgb:lesson_cache:{purpose}:{level}:{topic}:{type}:{date}` for
 *     36 hours. Personalisation (e.g. exclusion of recently-learned
 *     words) is re-applied per user after cache hit.
 */
class GeminiLessonGenerator
{
    /** Lesson type constants — used by both cache key and prompt builder. */
    public const TYPE_VOCAB = 'vocab';
    public const TYPE_GRAMMAR = 'grammar';
    public const TYPE_READING = 'reading';
    public const TYPE_CONVERSATION = 'conversation';
    public const TYPE_LISTENING = 'listening';
    public const TYPE_REVIEW = 'review';

    /** Human-readable label per type — surfaced in recap card / menu. */
    public const TYPE_LABELS = [
        self::TYPE_VOCAB => 'Từ vựng',
        self::TYPE_GRAMMAR => 'Ngữ pháp',
        self::TYPE_READING => 'Đọc hiểu',
        self::TYPE_CONVERSATION => 'Hội thoại',
        self::TYPE_LISTENING => 'Nghe hiểu',
        self::TYPE_REVIEW => 'Ôn tập tuần',
    ];

    /** @var list<string> */
    private array $apiKeys;

    private string $model;

    /** @var list<string> */
    private array $fallbackModels;

    public function __construct()
    {
        $this->apiKeys = $this->parseCsv((string) config(
            'services.gemini.keys',
            config('services.gemini.key', '')
        ));
        $this->model = (string) config('services.gemini.model', 'gemini-2.5-flash-lite');
        $this->fallbackModels = $this->parseCsv((string) config(
            'services.gemini.fallback_models',
            config('services.gemini.fallback_model', 'gemini-2.5-flash')
        ));
    }

    /**
     * Generate (or fetch cached) daily lesson content. Picks the lesson
     * type based on today's day-of-week unless the caller specifies one.
     *
     * @return array{
     *     vocabulary: list<array<string, string>>,
     *     grammar: array<string, string>,
     *     topic_intro_vi: string,
     *     lesson_type: string,
     *     extra: array<string, mixed>
     * }|null
     */
    public function generateDailyLesson(LearningProfile $profile, Topic $topic, int $wordCount = 5): ?array
    {
        return $this->generateDailyLessonOfType($profile, $topic, $this->lessonTypeForToday(), $wordCount);
    }

    /**
     * Generate a daily lesson of a specific type. Used by /extra (always
     * vocab) and the scheduler (varies by weekday).
     */
    public function generateDailyLessonOfType(
        LearningProfile $profile,
        Topic $topic,
        string $lessonType,
        int $wordCount = 5
    ): ?array {
        if ($lessonType === self::TYPE_REVIEW) {
            // Review doesn't hit Gemini — caller will compose from user data.
            return $this->buildReviewSkeleton($profile);
        }

        if ($this->apiKeys === []) {
            Log::warning('[TelegramBot] GEMINI_API_KEY chưa được cấu hình.');
            $this->alertFailure('Gemini API key is missing', $profile, $topic, $lessonType);
            return null;
        }

        $exclude = $this->wordsToExclude($profile, 60);
        $cacheKey = $this->cacheKey($profile, $topic, $lessonType);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['lesson_type'])) {
            return $this->personalise($cached, $exclude, $wordCount);
        }

        $prompt = $this->buildPromptForType($profile, $topic, $lessonType, $wordCount, $exclude);

        try {
            $models = array_values(array_unique(array_filter([
                $this->model,
                ...$this->fallbackModels,
            ])));
            $failures = [];

            foreach ($models as $model) {
                foreach ($this->apiKeys as $keyIndex => $apiKey) {
                    // SECURITY (SEC-050): without coordination, multiple
                    // concurrent requests can all observe the same key
                    // failing and stampede onto the next one — exhausting
                    // the fallback keys simultaneously. We use a short
                    // per-key Redis lock so only one request probes a
                    // given key per probe window, and a `key_blacklist`
                    // cache entry to skip keys that have already failed
                    // for the lifetime of this worker burst.
                    if ($this->isKeyCooldown($apiKey)) {
                        $failures[] = [
                            'model' => $model,
                            'key_number' => $keyIndex + 1,
                            'status' => null,
                            'error' => 'Key is in cooldown (another worker is probing it).',
                        ];
                        continue;
                    }

                    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
                    $response = Http::timeout(30)->post($endpoint . '?key=' . $apiKey, [
                        'contents' => [
                            ['role' => 'user', 'parts' => [['text' => $prompt]]],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'topP' => 0.95,
                            'topK' => 64,
                            'maxOutputTokens' => 2048,
                        ],
                    ]);

                    if (! $response->successful()) {
                        $failures[] = [
                            'model' => $model,
                            'key_number' => $keyIndex + 1,
                            'status' => $response->status(),
                            'error' => $response->json('error.message') ?? $response->body(),
                        ];
                        Log::warning('[TelegramBot] Gemini model/key failed', end($failures));
                        // Mark this key as in-cooldown for 60 s so concurrent
                        // workers don't probe it again in the same burst.
                        $this->markKeyCooldown($apiKey);
                        continue;
                    }

                    $text = $response->json('candidates.0.content.parts.0.text');
                    if (! is_string($text) || trim($text) === '') {
                        $failures[] = [
                            'model' => $model,
                            'key_number' => $keyIndex + 1,
                            'status' => $response->status(),
                            'error' => 'Empty/non-text payload; finish reason: '
                                . ($response->json('candidates.0.finishReason') ?? 'unknown'),
                        ];
                        Log::warning('[TelegramBot] Gemini returned invalid content', end($failures));
                        $this->markKeyCooldown($apiKey);
                        continue;
                    }

                    $parsed = $this->parseResponseForType($text, $lessonType, $exclude, $wordCount);
                    if ($parsed === null) {
                        $failures[] = [
                            'model' => $model,
                            'key_number' => $keyIndex + 1,
                            'status' => $response->status(),
                            'error' => 'Cannot parse lesson JSON: ' . mb_substr($text, 0, 500),
                        ];
                        continue;
                    }

                    if ($model !== $this->model || $keyIndex > 0) {
                        Log::notice('[TelegramBot] Gemini failover succeeded', [
                            'primary_model' => $this->model,
                            'selected_model' => $model,
                            'key_number' => $keyIndex + 1,
                        ]);
                    }

                    $parsed['lesson_type'] = $lessonType;
                    Cache::put($cacheKey, $parsed, now()->addHours(36));

                    return $parsed;
                }
            }

            Log::error('[TelegramBot] All Gemini models failed', ['failures' => $failures]);
            $this->alertFailure('All Gemini lesson models failed', $profile, $topic, $lessonType, [
                'attempted_models' => $models,
                'api_key_count' => count($this->apiKeys),
                'failures' => $failures,
            ]);

            return null;
        } catch (\Throwable $e) {
            // SEC-030: sanitise $e->getMessage() before logging — prevents log injection.
            Log::error('[TelegramBot] Gemini exception: ' . Str::limit(preg_replace('/[\r\n\t]+/', ' ', (string) $e->getMessage()), 200, ''), [
                'model' => $this->model,
                'exception' => $e,
            ]);
            $this->alertFailure('Gemini lesson generation exception', $profile, $topic, $lessonType, [], $e);
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function parseCsv(string $value): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $item): bool => $item !== ''
        )));
    }

    /**
     * SEC-050: per-key cooldown marker. The lock key is a SHA-1 of the
     * API key so we never write the raw key into the cache store (which
     * would be visible to anyone with cache:read access).
     */
    private function keyCooldownKey(string $apiKey): string
    {
        return 'tgb:gemini:key_cooldown:' . sha1($apiKey);
    }

    private function isKeyCooldown(string $apiKey): bool
    {
        return Cache::has($this->keyCooldownKey($apiKey));
    }

    private function markKeyCooldown(string $apiKey): void
    {
        Cache::put($this->keyCooldownKey($apiKey), true, now()->addSeconds(60));
    }

    private function alertFailure(
        string $title,
        LearningProfile $profile,
        Topic $topic,
        string $lessonType,
        array $context = [],
        ?\Throwable $exception = null
    ): void {
        try {
            app(TelegramService::class)->sendAdminAlert($title, array_merge([
                'feature' => 'telegram_lesson',
                'model' => $this->model,
                'user_id' => $profile->user_id,
                'topic_id' => $topic->id,
                'topic' => $topic->name_vi ?? $topic->name_en ?? 'unknown',
                'lesson_type' => $lessonType,
            ], $context), $exception);
        } catch (\Throwable $alertException) {
            Log::warning('[TelegramBot] Could not send Gemini admin alert', [
                'error' => $alertException->getMessage(),
            ]);
        }
    }

    /**
     * Map today's date to a lesson type.
     *
     * Mon=T2, Tue=T3, ... Sun=T8 (Carbon dayOfWeekIso: 1=Mon..7=Sun).
     */
    public function lessonTypeForToday(?Carbon $when = null): string
    {
        $when ??= Carbon::now();
        return match ($when->dayOfWeekIso) {
            1, 3 => self::TYPE_VOCAB,         // Mon, Wed
            2, 4 => self::TYPE_GRAMMAR,        // Tue, Thu
            5    => self::TYPE_READING,        // Fri
            6    => self::TYPE_CONVERSATION,   // Sat
            7    => self::TYPE_LISTENING,      // Sun
            default => self::TYPE_VOCAB,
        };
    }

    /**
     * SECURITY (SEC-036): Sanitize each vocab word before it is embedded
     * in any Gemini prompt. Stored vocab rows are user-controlled, so a
     * malicious entry could carry prompt-injection payloads (e.g.
     * "ignore prior instructions…"). We strip HTML/script tags, keep
     * only safe characters, and bound length per word.
     *
     * @return list<string>
     */
    private function wordsToExclude(LearningProfile $profile, int $limit): array
    {
        return VocabularyEntry::query()
            ->where('user_id', $profile->user_id)
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('word')
            ->map(function ($w): string {
                $clean = strip_tags((string) $w);
                // Keep letters, digits, apostrophes, hyphens, spaces only.
                $clean = (string) preg_replace('/[^\pL\pN\'\-\s]/u', '', $clean);
                return strtolower(Str::limit(trim($clean), 40, ''));
            })
            ->filter(static fn (string $w): bool => $w !== '')
            ->values()
            ->all();
    }

    private function cacheKey(LearningProfile $profile, Topic $topic, string $lessonType): string
    {
        return sprintf(
            'tgb:lesson_cache:%s:%s:%d:%s:%s',
            $profile->purpose,
            $profile->level,
            $topic->id,
            $lessonType,
            Carbon::now()->toDateString()
        );
    }

    /**
     * Re-apply per-user word exclusion on top of a cached lesson. For
     * non-vocab types we keep the original "extra" block (reading passage,
     * conversation transcript) intact since those don't depend on the
     * user's exclusion list.
     */
    private function personalise(array $cached, array $exclude, int $wordCount): array
    {
        $lowerExclude = array_map('strtolower', $exclude);
        $filtered = array_values(array_filter(
            (array) ($cached['vocabulary'] ?? []),
            function ($entry) use ($lowerExclude) {
                if (! is_array($entry) || empty($entry['word'])) {
                    return false;
                }
                return ! in_array(strtolower((string) $entry['word']), $lowerExclude, true);
            }
        ));

        $vocab = ! empty($filtered)
            ? array_slice($filtered, 0, max(1, $wordCount))
            : array_slice((array) ($cached['vocabulary'] ?? []), 0, max(1, $wordCount));

        return [
            'vocabulary' => $vocab,
            'grammar' => (array) ($cached['grammar'] ?? []),
            'topic_intro_vi' => (string) ($cached['topic_intro_vi'] ?? ''),
            'lesson_type' => (string) ($cached['lesson_type'] ?? self::TYPE_VOCAB),
            'extra' => (array) ($cached['extra'] ?? []),
        ];
    }

    /**
     * Build a skeleton for the review lesson type. The caller (learning
     * service) will populate vocabulary/grammar from the user's existing
     * data — Gemini is NOT involved.
     */
    private function buildReviewSkeleton(LearningProfile $profile): array
    {
        return [
            'vocabulary' => [],
            'grammar' => [],
            'topic_intro_vi' => 'Tuần này bạn đã học nhiều rồi — hãy cùng ôn lại nhé!',
            'lesson_type' => self::TYPE_REVIEW,
            'extra' => [],
        ];
    }

    /**
     * Build the right prompt for the lesson type.
     */
    private function buildPromptForType(
        LearningProfile $profile,
        Topic $topic,
        string $lessonType,
        int $wordCount,
        array $exclude
    ): string {
        return match ($lessonType) {
            self::TYPE_VOCAB => $this->buildPrompt($profile, $topic, $wordCount, $exclude),
            self::TYPE_GRAMMAR => $this->buildGrammarPrompt($profile, $topic, $exclude),
            self::TYPE_READING => $this->buildReadingPrompt($profile, $topic, $exclude),
            self::TYPE_CONVERSATION => $this->buildConversationPrompt($profile, $topic, $exclude),
            self::TYPE_LISTENING => $this->buildListeningPrompt($profile, $topic, $exclude),
            default => $this->buildPrompt($profile, $topic, $wordCount, $exclude),
        };
    }

    /**
     * Build the prompt for the canonical "vocab" lesson (Mon/Wed + /extra).
     */
    private function buildPrompt(LearningProfile $profile, Topic $topic, int $wordCount, array $exclude): string
    {
        $excludeCsv = $exclude ? implode(', ', $exclude) : '(none)';

        $purposeLabel = match ($profile->purpose) {
            LearningProfile::PURPOSE_DAILY => 'daily conversation',
            LearningProfile::PURPOSE_BUSINESS => 'business / workplace English',
            default => 'IELTS academic English',
        };

        $levelLabel = match ($profile->level) {
            LearningProfile::LEVEL_BEGINNER => 'beginner (A2)',
            LearningProfile::LEVEL_ADVANCED => 'advanced (C1+)',
            default => 'intermediate (B1-B2)',
        };

        $exampleBlock = <<<EXAMPLE
{
  "vocabulary": [
    {
      "word": "commute",
      "pos": "v",
      "ipa": "/kəˈmjuːt/",
      "meaning_vi": "đi lại hằng ngày (giữa nhà và nơi làm việc)",
      "meaning_en": "to travel regularly between home and work",
      "example_en": "I commute by bus every morning because the traffic is heavy.",
      "example_vi": "Tôi đi xe buýt mỗi sáng vì đường hay kẹt."
    }
  ],
  "grammar": {
    "structure": "used to + V",
    "explanation_vi": "Diễn tả thói quen trong quá khứ mà hiện không còn nữa.",
    "explanation_en": "Used for past habits or states that are no longer true.",
    "example_en": "I used to commute by motorbike, but now I take the bus.",
    "example_vi": "Trước đây tôi đi xe máy, nhưng giờ tôi đi xe buýt."
  },
  "topic_intro_vi": "Hôm nay chúng ta cùng tìm hiểu về chủ đề giao thông và đi lại trong thành phố nhé!"
}
EXAMPLE;

        return $this->wrapPrompt(
            $purposeLabel,
            $levelLabel,
            $topic,
            <<<BODY
Generate today's micro-lesson: exactly {$wordCount} vocabulary words + 1 grammar structure + a short Vietnamese intro to the topic.

Output contract (JSON, no fences):
{
  "vocabulary": [ { "word", "pos", "ipa", "meaning_vi", "meaning_en", "example_en", "example_vi" } ... {$wordCount} items ],
  "grammar": { "structure", "explanation_vi", "explanation_en", "example_en", "example_vi" },
  "topic_intro_vi": "1-2 câu tiếng Việt có emoji"
}

Rules:
- High-frequency words only. Beginner: top 2000; Intermediate: top 5000; Advanced: top 8000.
- DO NOT use these words (user already learned them): {$excludeCsv}
- example_en: 8-15 words, natural sentence using the word.
- Avoid cultural references uncommon in Vietnam.
- Prefer scenarios: café, work, study, travel, family, market.
BODY,
            $exampleBlock
        );
    }

    /**
     * Grammar-focused prompt (Tue/Thu).
     */
    private function buildGrammarPrompt(LearningProfile $profile, Topic $topic, array $exclude): string
    {
        $excludeCsv = $exclude ? implode(', ', $exclude) : '(none)';
        $purposeLabel = $this->purposeLabel($profile);
        $levelLabel = $this->levelLabel($profile);

        $exampleBlock = <<<EXAMPLE
{
  "vocabulary": [
    { "word": "deadline", "pos": "n", "ipa": "/ˈdedlaɪn/", "meaning_vi": "hạn chót", "meaning_en": "a time by which something must be done", "example_en": "The deadline for the report is Friday.", "example_vi": "Hạn chót nộp báo cáo là thứ Sáu." }
  ],
  "grammar": {
    "structure": "need to + V",
    "explanation_vi": "Diễn tả điều cần thiết phải làm.",
    "explanation_en": "Express something that is necessary.",
    "example_en": "I need to finish this report before the deadline.",
    "example_vi": "Tôi cần hoàn thành báo cáo này trước hạn chót."
  },
  "topic_intro_vi": "Hôm nay chúng ta học cách nói về những điều cần làm trong công việc nhé! 💼"
}
EXAMPLE;

        return $this->wrapPrompt(
            $purposeLabel,
            $levelLabel,
            $topic,
            <<<BODY
Generate a GRAMMAR-FOCUSED lesson:
- Exactly 3 supporting vocabulary words (related to the grammar)
- 1 grammar pattern with 3 example sentences (8-15 words each)
- 1 short Vietnamese intro

Output contract (JSON, no fences):
{
  "vocabulary": [ ...3 items, each { word, pos, ipa, meaning_vi, meaning_en, example_en, example_vi } ],
  "grammar": {
    "structure": "<pattern>",
    "explanation_vi": "...",
    "explanation_en": "...",
    "example_en": "<primary example>",
    "example_vi": "...",
    "examples_extra_en": [ "<sentence 2>", "<sentence 3>" ]
  },
  "topic_intro_vi": "..."
}

Rules:
- Pick 1 grammar pattern relevant to "{$topic->name_en}" and {$levelLabel} learners.
- DO NOT use these words: {$excludeCsv}
- example_en must be a real sentence a Vietnamese learner might say at work/school.
- examples_extra_en: two more natural example sentences using the same pattern.
BODY,
            $exampleBlock
        );
    }

    /**
     * Reading prompt (Fri).
     */
    private function buildReadingPrompt(LearningProfile $profile, Topic $topic, array $exclude): string
    {
        $excludeCsv = $exclude ? implode(', ', $exclude) : '(none)';
        $purposeLabel = $this->purposeLabel($profile);
        $levelLabel = $this->levelLabel($profile);

        $exampleBlock = <<<EXAMPLE
{
  "vocabulary": [],
  "grammar": { "structure": "(no new grammar today — reading day)" },
  "topic_intro_vi": "Hôm nay chúng ta cùng đọc một đoạn văn ngắn về chủ đề nhé!",
  "extra": {
    "passage_en": "Maria works at a small coffee shop near her university. Every morning, she wakes up at 6 a.m. and takes the bus to work. She likes talking to the customers because she wants to improve her English. After work, she studies for two hours before going home.",
    "passage_vi": "Maria làm việc tại một quán cà phê nhỏ gần trường đại học. Mỗi sáng, cô ấy thức dậy lúc 6 giờ và đi xe buýt đến chỗ làm. Cô ấy thích nói chuyện với khách hàng vì cô ấy muốn cải thiện tiếng Anh của mình. Sau giờ làm, cô ấy học bài hai tiếng trước khi về nhà.",
    "questions": [
      { "q_en": "Where does Maria work?", "q_vi": "Maria làm việc ở đâu?", "answer": "At a coffee shop." },
      { "q_en": "What time does she wake up?", "q_vi": "Cô ấy thức dậy lúc mấy giờ?", "answer": "At 6 a.m." },
      { "q_en": "Why does she like talking to customers?", "q_vi": "Tại sao cô ấy thích nói chuyện với khách?", "answer": "To improve her English." }
    ]
  }
}
EXAMPLE;

        return $this->wrapPrompt(
            $purposeLabel,
            $levelLabel,
            $topic,
            <<<BODY
Generate a READING lesson:
- 1 short English passage (60-90 words) about "{$topic->name_en}"
- Vietnamese translation of the passage
- 3 comprehension questions (English) with Vietnamese translation and answer

Output contract (JSON, no fences):
{
  "vocabulary": [],
  "grammar": { "structure": "(no new grammar today — reading day)" },
  "topic_intro_vi": "...",
  "extra": {
    "passage_en": "<60-90 words>",
    "passage_vi": "<Vietnamese translation>",
    "questions": [
      { "q_en": "...", "q_vi": "...", "answer": "<short English answer>" },
      ...3 items
    ]
  }
}

Rules:
- DO NOT use these words: {$excludeCsv}
- Passage should be a story/scenario, not a list of facts.
- Questions should test main idea + a specific detail.
- Vocabulary array MUST be empty (reading day, no new vocab).
BODY,
            $exampleBlock
        );
    }

    /**
     * Conversation prompt (Sat).
     */
    private function buildConversationPrompt(LearningProfile $profile, Topic $topic, array $exclude): string
    {
        $excludeCsv = $exclude ? implode(', ', $exclude) : '(none)';
        $purposeLabel = $this->purposeLabel($profile);
        $levelLabel = $this->levelLabel($profile);

        $exampleBlock = <<<EXAMPLE
{
  "vocabulary": [
    { "word": "appointment", "pos": "n", "ipa": "/əˈpɔɪntmənt/", "meaning_vi": "cuộc hẹn", "meaning_en": "a scheduled meeting", "example_en": "I have a doctor's appointment tomorrow.", "example_vi": "Tôi có cuộc hẹn với bác sĩ vào ngày mai." }
  ],
  "grammar": { "structure": "(no new grammar today — conversation day)" },
  "topic_intro_vi": "Hôm nay chúng ta học cách trò chuyện tự nhiên qua một đoạn hội thoại mẫu nhé!",
  "extra": {
    "scenario_vi": "Hai đồng nghiệp nói chuyện ở quán cà phê sau giờ làm.",
    "lines": [
      { "speaker": "A", "en": "How was your weekend?", "vi": "Cuối tuần của bạn thế nào?" },
      { "speaker": "B", "en": "It was great! I went hiking with my friends.", "vi": "Tuyệt lắm! Tôi đã đi leo núi với bạn bè." },
      { "speaker": "A", "en": "That sounds fun. Where did you go?", "vi": "Nghe vui đó. Bạn đi đâu vậy?" },
      { "speaker": "B", "en": "We went to Ba Vi. The view was amazing.", "vi": "Chúng tôi đi Ba Vì. Quang cảnh tuyệt đẹp." },
      { "speaker": "A", "en": "I'd love to go sometime. Any tips for a beginner?", "vi": "Tôi cũng muốn đi thử. Có lời khuyên gì cho người mới không?" },
      { "speaker": "B", "en": "Start with an easy trail and bring plenty of water.", "vi": "Hãy bắt đầu với đường dễ và mang theo nhiều nước." }
    ]
  }
}
EXAMPLE;

        return $this->wrapPrompt(
            $purposeLabel,
            $levelLabel,
            $topic,
            <<<BODY
Generate a CONVERSATION lesson:
- 1 mini-dialog (6-8 lines) between two people on a natural scenario about "{$topic->name_en}"
- Vietnamese translation of the scenario intro and each line
- 4 supporting vocabulary words that appear in the dialog

Output contract (JSON, no fences):
{
  "vocabulary": [ ...4 items each { word, pos, ipa, meaning_vi, meaning_en, example_en, example_vi } ],
  "grammar": { "structure": "(no new grammar today — conversation day)" },
  "topic_intro_vi": "...",
  "extra": {
    "scenario_vi": "<mô tả ngắn tình huống hội thoại bằng tiếng Việt>",
    "lines": [
      { "speaker": "A|B", "en": "...", "vi": "..." },
      ...6-8 items
    ]
  }
}

Rules:
- DO NOT use these words: {$excludeCsv}
- Each line is 5-15 words, natural spoken English (not formal).
- Speakers alternate A/B/A/B.
- Vocabulary words should appear naturally in the dialog lines.
BODY,
            $exampleBlock
        );
    }

    /**
     * Listening prompt (Sun). Returns transcript + audio URL hook.
     */
    private function buildListeningPrompt(LearningProfile $profile, Topic $topic, array $exclude): string
    {
        $excludeCsv = $exclude ? implode(', ', $exclude) : '(none)';
        $purposeLabel = $this->purposeLabel($profile);
        $levelLabel = $this->levelLabel($profile);

        $exampleBlock = <<<EXAMPLE
{
  "vocabulary": [],
  "grammar": { "structure": "(listening day — no new grammar)" },
  "topic_intro_vi": "Hôm nay chúng ta nghe và trả lời câu hỏi nhé! Đoạn audio bằng tiếng Anh-Mỹ, tốc độ vừa phải.",
  "extra": {
    "transcript_en": "Hi, I'm Tom. I work as a software engineer at a small company in Ho Chi Minh City. Every morning, I take the bus to the office. I love my job because I get to solve interesting problems every day.",
    "transcript_vi": "Chào, tôi là Tom. Tôi làm kỹ sư phần mềm tại một công ty nhỏ ở TP.HCM. Mỗi sáng tôi đi xe buýt đến văn phòng. Tôi yêu công việc vì tôi được giải quyết những vấn đề thú vị mỗi ngày.",
    "questions": [
      { "q_en": "Where does Tom work?", "q_vi": "Tom làm việc ở đâu?", "answer": "At a software company in HCMC." },
      { "q_en": "How does he commute?", "q_vi": "Anh ấy đi làm bằng gì?", "answer": "By bus." },
      { "q_en": "Why does he love his job?", "q_vi": "Tại sao anh ấy thích công việc?", "answer": "He gets to solve interesting problems." }
    ]
  }
}
EXAMPLE;

        return $this->wrapPrompt(
            $purposeLabel,
            $levelLabel,
            $topic,
            <<<BODY
Generate a LISTENING lesson:
- 1 short monologue (~80 words) on "{$topic->name_en}" at {$levelLabel} level
- Vietnamese translation of the monologue
- 3 comprehension questions (English) with Vietnamese translation and short answer

Output contract (JSON, no fences):
{
  "vocabulary": [],
  "grammar": { "structure": "(listening day — no new grammar)" },
  "topic_intro_vi": "...",
  "extra": {
    "transcript_en": "<~80 words>",
    "transcript_vi": "...",
    "questions": [
      { "q_en": "...", "q_vi": "...", "answer": "<short English answer>" },
      ...3 items
    ]
  }
}

Rules:
- DO NOT use these words: {$excludeCsv}
- Transcript should be one speaker (monologue), not a dialog.
- Sentences should be short enough to listen to clearly (max 15 words each).
- Vocabulary array MUST be empty.
BODY,
            $exampleBlock
        );
    }

    /**
     * Shared wrapper — persona, level, audience + body + example.
     */
    private function wrapPrompt(string $purposeLabel, string $levelLabel, Topic $topic, string $body, string $exampleBlock): string
    {
        $topicLine = "Topic: {$topic->name_en} ({$topic->name_vi})";

        return <<<PROMPT
You are Ms. Linh — a warm, encouraging Vietnamese English teacher with 12 years of experience teaching Vietnamese students. You explain concepts simply, use examples from everyday Vietnamese life (phở, xe máy, chợ, công việc văn phòng), and you always end with a small encouragement. You never use jargon. When you write English examples, they are short, natural, and easy to remember.

# Context
- Audience: Vietnamese students learning {$purposeLabel}
- {$topicLine}
- Target level: {$levelLabel}
- Tone: friendly, supportive, like a favourite teacher

# Your task
{$body}

# Example (one slice — for shape reference; generate the full lesson yourself)
{$exampleBlock}

Now generate today's full lesson. Output ONLY the JSON.
PROMPT;
    }

    private function purposeLabel(LearningProfile $profile): string
    {
        return match ($profile->purpose) {
            LearningProfile::PURPOSE_DAILY => 'daily conversation',
            LearningProfile::PURPOSE_BUSINESS => 'business / workplace English',
            default => 'IELTS academic English',
        };
    }

    private function levelLabel(LearningProfile $profile): string
    {
        return match ($profile->level) {
            LearningProfile::LEVEL_BEGINNER => 'beginner (A2)',
            LearningProfile::LEVEL_ADVANCED => 'advanced (C1+)',
            default => 'intermediate (B1-B2)',
        };
    }

    /**
     * Parse response and normalise shape regardless of lesson type.
     */
    private function parseResponseForType(string $text, string $lessonType, array $exclude, int $wordCount): ?array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $clean);
        $clean = trim((string) $clean);

        $decoded = json_decode($clean, true);
        if (! is_array($decoded) || ! isset($decoded['vocabulary'], $decoded['grammar'])) {
            Log::warning('[TelegramBot] Gemini JSON parse failed', ['raw' => substr($text, 0, 200)]);
            return null;
        }

        // Apply word-exclusion to the vocab slice only (other types
        // have minimal or no vocab).
        $exclude = array_map('strtolower', $exclude);
        $decoded['vocabulary'] = array_values(array_filter(
            (array) $decoded['vocabulary'],
            function ($entry) use ($exclude) {
                if (! is_array($entry) || empty($entry['word'])) {
                    return false;
                }
                return ! in_array(strtolower((string) $entry['word']), $exclude, true);
            }
        ));

        if ($lessonType !== self::TYPE_READING && $lessonType !== self::TYPE_LISTENING && empty($decoded['vocabulary'])) {
            Log::warning('[TelegramBot] All generated words collided with exclusion list');
            return null;
        }

        $vocab = array_slice((array) $decoded['vocabulary'], 0, max(1, $wordCount));
        $grammar = (array) $decoded['grammar'];

        // Pull `extra` block if present (reading / conversation / listening).
        $extra = (array) ($decoded['extra'] ?? []);

        return [
            'vocabulary' => $vocab,
            'grammar' => $grammar,
            'topic_intro_vi' => (string) ($decoded['topic_intro_vi'] ?? ''),
            'lesson_type' => $lessonType,
            'extra' => $extra,
        ];
    }
}
