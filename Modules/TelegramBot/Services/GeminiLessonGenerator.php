<?php

namespace Modules\TelegramBot\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Models\LearningProfile;
use Modules\TelegramBot\Models\Topic;
use Modules\TelegramBot\Models\VocabularyEntry;

/**
 * Calls Gemini to generate a daily micro-lesson for a user.
 * Pattern mirrors Modules/Speaking/Services/AiSpeakingService::generate().
 */
class GeminiLessonGenerator
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.key', '');
        $this->model = (string) config('services.gemini.model', 'gemini-2.5-flash-lite');
        $this->endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";
    }

    /**
     * Generate a daily lesson.
     *
     * @return array{
     *     vocabulary: list<array<string, string>>,
     *     grammar: array<string, string>,
     *     topic_intro_vi: string
     * }|null
     */
    public function generateDailyLesson(LearningProfile $profile, Topic $topic, int $wordCount = 5): ?array
    {
        if ($this->apiKey === '') {
            Log::warning('[TelegramBot] GEMINI_API_KEY chưa được cấu hình.');
            return null;
        }

        $exclude = $this->wordsToExclude($profile, 60);
        $prompt = $this->buildPrompt($profile, $topic, $wordCount, $exclude);

        try {
            $response = Http::timeout(30)->post($this->endpoint . '?key=' . $this->apiKey, [
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
                Log::error('[TelegramBot] Gemini error', ['body' => $response->body()]);
                return null;
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            if (! is_string($text)) {
                Log::error('[TelegramBot] Gemini returned non-text payload');
                return null;
            }

            return $this->parseResponse($text, $exclude, $wordCount);
        } catch (\Throwable $e) {
            Log::error('[TelegramBot] Gemini exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function wordsToExclude(LearningProfile $profile, int $limit): array
    {
        return VocabularyEntry::query()
            ->where('user_id', $profile->user_id)
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('word')
            ->map(fn ($w) => strtolower((string) $w))
            ->all();
    }

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

        return <<<PROMPT
You are an English learning assistant for Vietnamese students learning {$purposeLabel}.

Topic: {$topic->name_en} ({$topic->name_vi})
Level: {$levelLabel}

Generate today's micro-lesson. Return ONLY valid JSON with this structure:

{
  "vocabulary": [
    {
      "word": "string",
      "pos": "n|v|adj|adv|phrasal",
      "ipa": "/string/",
      "meaning_vi": "nghĩa tiếng Việt",
      "meaning_en": "English definition",
      "example_en": "example sentence using the word naturally",
      "example_vi": "bản dịch tiếng Việt"
    }
    // {$wordCount} items
  ],
  "grammar": {
    "structure": "It's high time + S + V2",
    "explanation_vi": "giải thích ngắn gọn bằng tiếng Việt",
    "explanation_en": "concise English explanation",
    "example_en": "It's high time we invested in renewable energy.",
    "example_vi": "Đã đến lúc chúng ta đầu tư vào năng lượng tái tạo."
  },
  "topic_intro_vi": "1-2 câu giới thiệu chủ đề hôm nay"
}

Rules:
- Vocabulary must match the level and topic.
- Do NOT use these words already learned: {$excludeCsv}
- Examples should be practical and IELTS-style if purpose=ielts.
- Output ONLY JSON, no markdown fences.
PROMPT;
    }

    /**
     * @param list<string> $exclude
     * @return array<string, mixed>|null
     */
    private function parseResponse(string $text, array $exclude, int $wordCount): ?array
    {
        $clean = trim($text);
        // Strip accidental markdown fences.
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $clean);
        $clean = trim((string) $clean);

        $decoded = json_decode($clean, true);
        if (! is_array($decoded) || ! isset($decoded['vocabulary'], $decoded['grammar'])) {
            Log::warning('[TelegramBot] Gemini JSON parse failed', ['raw' => substr($text, 0, 200)]);
            return null;
        }

        // Filter out any words that collide with the exclusion list (case-insensitive).
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

        // If filtering removed everything, fall back to original (degraded but not empty).
        if (empty($decoded['vocabulary'])) {
            Log::warning('[TelegramBot] All generated words collided with exclusion list');
            return null;
        }

        return [
            'vocabulary' => array_slice((array) $decoded['vocabulary'], 0, max(1, $wordCount)),
            'grammar' => (array) $decoded['grammar'],
            'topic_intro_vi' => (string) ($decoded['topic_intro_vi'] ?? ''),
        ];
    }
}
