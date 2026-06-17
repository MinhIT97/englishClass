<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Models\DailyLesson;
use Modules\TelegramBot\Models\GrammarEntry;
use Modules\TelegramBot\Models\LearningProfile;
use Modules\TelegramBot\Models\ReviewSchedule;
use Modules\TelegramBot\Models\Topic;
use Modules\TelegramBot\Models\UserPath;
use Modules\TelegramBot\Models\UserTelegramLink;
use Modules\TelegramBot\Models\VocabularyEntry;

/**
 * Orchestrates daily lesson creation + delivery to a user.
 */
class TelegramLearningService
{
    public function __construct(
        private readonly GeminiLessonGenerator $generator,
        private readonly TelegramService $telegram,
    ) {
    }

    /**
     * Find the user's "current" topic; create one if none exists.
     */
    public function getOrAssignCurrentTopic(User $user, LearningProfile $profile): ?Topic
    {
        $current = UserPath::query()
            ->where('user_id', $user->id)
            ->where('status', UserPath::STATUS_CURRENT)
            ->first();

        if ($current) {
            return $current->topic;
        }

        $next = UserPath::query()
            ->where('user_id', $user->id)
            ->where('status', UserPath::STATUS_LOCKED)
            ->join('tgb_topics', 'tgb_topics.id', '=', 'tgb_user_paths.topic_id')
            ->where('tgb_topics.purpose', $profile->purpose)
            ->orderBy('tgb_topics.order_index')
            ->select('tgb_user_paths.*')
            ->first();

        if (! $next) {
            return null;
        }

        $next->status = UserPath::STATUS_CURRENT;
        $next->started_at = Carbon::now();
        $next->save();

        return $next->topic;
    }

    /**
     * Mark a topic as completed for the user (called from review/quiz flows).
     */
    public function completeCurrentTopicIfEligible(User $user, Topic $topic): bool
    {
        $path = UserPath::query()
            ->where('user_id', $user->id)
            ->where('topic_id', $topic->id)
            ->where('status', UserPath::STATUS_CURRENT)
            ->first();

        if (! $path) {
            return false;
        }

        $totalWords = VocabularyEntry::query()
            ->where('user_id', $user->id)
            ->where('topic_id', $topic->id)
            ->count();

        if ($totalWords === 0) {
            return false;
        }

        $matureWords = ReviewSchedule::query()
            ->where('user_id', $user->id)
            ->whereHas('vocabularyEntry', function ($q) use ($user, $topic) {
                $q->where('user_id', $user->id)->where('topic_id', $topic->id);
            })
            ->where('repetitions', '>=', 2)
            ->count();

        if ($matureWords < $totalWords) {
            return false;
        }

        $path->status = UserPath::STATUS_COMPLETED;
        $path->completed_at = Carbon::now();
        $path->save();

        return true;
    }

    /**
     * Build + send today's lesson to a single user.
     *
     * @return bool true on successful send
     */
    public function sendDailyLesson(User $user, ?Carbon $when = null): bool
    {
        $when ??= Carbon::now();

        $profile = LearningProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $profile || $profile->is_paused) {
            return false;
        }

        $link = UserTelegramLink::query()->where('user_id', $user->id)->first();
        if (! $link) {
            return false;
        }

        // Avoid duplicates on the same date.
        $alreadySent = DailyLesson::query()
            ->where('user_id', $user->id)
            ->whereDate('lesson_date', $when->toDateString())
            ->where('status', DailyLesson::STATUS_SENT)
            ->exists();

        if ($alreadySent) {
            return false;
        }

        $topic = $this->getOrAssignCurrentTopic($user, $profile);
        if (! $topic) {
            Log::warning('[TelegramBot] No topic available for user', ['user_id' => $user->id]);
            return false;
        }

        $lesson = DailyLesson::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'lesson_date' => $when->toDateString(),
            ],
            [
                'topic_id' => $topic->id,
                'status' => DailyLesson::STATUS_SCHEDULED,
            ]
        );

        $payload = $this->generator->generateDailyLesson($profile, $topic, $pathWordCount = 5);
        if (! $payload) {
            $lesson->status = DailyLesson::STATUS_FAILED;
            $lesson->error_message = 'Gemini generation failed';
            $lesson->save();

            $this->telegram->sendMessage(
                $link->telegram_chat_id,
                "⚠️ <b>Bài học hôm nay chưa sẵn sàng.</b>\n\n"
                . "Hệ thống AI đang gặp vấn đề. Bạn có thể:\n"
                . "• Thử lại sau với /vocab\n"
                . "• Mở web app để xem từ vựng có sẵn\n"
                . "• Liên hệ admin nếu lỗi kéo dài",
                [
                    'inline_keyboard' => [
                        [
                            ['text' => '🌐 Mở web app', 'url' => url('/student/dashboard')],
                            ['text' => '🔁 Thử lại /vocab', 'callback_data' => 'tgb:vocab-detail'],
                        ],
                    ],
                ]
            );
            return false;
        }

        // Persist vocabulary + grammar + initial review schedules.
        DB::transaction(function () use ($user, $topic, $payload, $lesson) {
            foreach ($payload['vocabulary'] as $word) {
                $entry = VocabularyEntry::query()->updateOrCreate(
                    ['user_id' => $user->id, 'word' => $word['word']],
                    [
                        'topic_id' => $topic->id,
                        'pos' => $word['pos'] ?? null,
                        'ipa' => $word['ipa'] ?? null,
                        'meaning_vi' => $word['meaning_vi'] ?? '',
                        'meaning_en' => $word['meaning_en'] ?? null,
                        'example_en' => $word['example_en'] ?? null,
                        'example_vi' => $word['example_vi'] ?? null,
                        'difficulty' => $topic->difficulty,
                    ]
                );

                ReviewSchedule::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'vocabulary_entry_id' => $entry->id,
                    ],
                    [
                        'ease_factor' => 2.50,
                        'interval_days' => 0,
                        'repetitions' => 0,
                        'next_review_at' => Carbon::now()->addDay(),
                    ]
                );
            }

            $grammar = $payload['grammar'];
            GrammarEntry::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'topic_id' => $topic->id,
                    'structure' => $grammar['structure'] ?? '',
                ],
                [
                    'explanation_vi' => $grammar['explanation_vi'] ?? null,
                    'explanation_en' => $grammar['explanation_en'] ?? null,
                    'example_en' => $grammar['example_en'] ?? null,
                    'example_vi' => $grammar['example_vi'] ?? null,
                    'difficulty' => $topic->difficulty,
                ]
            );
        });

        // Send 3 separate messages: intro, vocabulary, grammar.
        $lastResponse = $this->sendIntroMessage($link->telegram_chat_id, $user, $topic, $payload);
        if ($lastResponse === null) {
            $lesson->status = DailyLesson::STATUS_FAILED;
            $lesson->error_message = 'Telegram send failed (intro)';
            $lesson->save();
            return false;
        }

        $this->sendVocabularyMessage($link->telegram_chat_id, $topic, $payload);

        $this->sendGrammarMessage($link->telegram_chat_id, $topic, $payload, $lesson->id);

        $lesson->status = DailyLesson::STATUS_SENT;
        $lesson->telegram_message_id = $lastResponse['result']['message_id'] ?? null;
        $lesson->sent_at = Carbon::now();
        $lesson->save();

        $this->bumpStreak($user);

        return true;
    }

    public function bumpStreak(User $user): void
    {
        $today = Carbon::now()->toDateString();
        $lastKey = "tgb:last_lesson:{$user->id}";
        $lastDate = cache()->get($lastKey);

        if ($lastDate === $today) {
            return; // already counted today
        }

        if ($lastDate === Carbon::now()->subDay()->toDateString()) {
            $user->streak = ($user->streak ?? 0) + 1;
        } else {
            $user->streak = 1;
        }
        $user->xp = ($user->xp ?? 0) + 10; // daily lesson XP
        $user->save();

        cache()->put($lastKey, $today, now()->addDays(2));
    }

    /**
     * Send message 1 of 3: greeting + topic intro.
     *
     * @param array{vocabulary: list<array<string, string>>, grammar: array<string, string>, topic_intro_vi: string} $payload
     */
    private function sendIntroMessage(string $chatId, User $user, Topic $topic, array $payload): ?array
    {
        $hour = (int) Carbon::now()->format('G');
        $greeting = match (true) {
            $hour < 5 => '🌙 Chào buổi đêm',
            $hour < 11 => '☀️ Chào buổi sáng',
            $hour < 14 => '🌤️ Chào buổi trưa',
            $hour < 18 => '🌅 Chào buổi chiều',
            default => '🌙 Chào buổi tối',
        };

        $text = "━━━━━━━━━━━━━━━━━━━━\n"
            . "{$greeting}, <b>{$user->name}</b>! 👋\n"
            . "📅 " . Carbon::now()->format('l, d/m/Y') . "\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "📌 <b>Chủ đề hôm nay:</b>\n"
            . "<b>{$topic->name_vi}</b> <i>({$topic->name_en})</i>\n\n"
            . (! empty($payload['topic_intro_vi'])
                ? "💭 <i>{$payload['topic_intro_vi']}</i>\n\n"
                : '')
            . "📦 <b>Bài học gồm 3 phần:</b>\n"
            . "1️⃣ Từ vựng mới\n"
            . "2️⃣ Cấu trúc câu hay\n"
            . "3️⃣ Quiz luyện tập\n\n"
            . "⬇️ <i>Xem bên dưới...</i>";

        return $this->telegram->sendMessage($chatId, $text);
    }

    /**
     * Send message 2 of 3: vocabulary card.
     *
     * @param array{vocabulary: list<array<string, string>>, grammar: array<string, string>, topic_intro_vi: string} $payload
     */
    private function sendVocabularyMessage(string $chatId, Topic $topic, array $payload): void
    {
        $words = array_slice($payload['vocabulary'], 0, 5);
        $count = count($words);

        $lines = [];
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "📚 <b>TỪ VỰNG MỚI</b> <i>({$count} từ)</i>";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "";

        foreach ($words as $i => $w) {
            $num = $i + 1;
            $ipa = ! empty($w['ipa']) ? " <code>{$w['ipa']}</code>" : '';
            $pos = ! empty($w['pos']) ? " <i>[{$w['pos']}]</i>" : '';
            $lines[] = "<b>{$num}. {$w['word']}</b>{$pos}{$ipa}";
            $lines[] = "   🇻🇳 {$w['meaning_vi']}";
            if (! empty($w['example_en'])) {
                $lines[] = "   💬 <i>\"{$w['example_en']}\"</i>";
            }
            if ($i < count($words) - 1) {
                $lines[] = "";
            }
        }

        $lines[] = "";
        $lines[] = "💡 <i>Bấm nút bên dưới để xem chi tiết hoặc làm quiz.</i>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📖 Xem chi tiết từng từ', 'callback_data' => 'tgb:vocab-detail'],
                ],
                [
                    ['text' => '📝 Làm quiz ngay', 'callback_data' => 'tgb:q:start'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, implode("\n", $lines), $keyboard);
    }

    /**
     * Send message 3 of 3: grammar structure with example.
     *
     * @param array{vocabulary: list<array<string, string>>, grammar: array<string, string>, topic_intro_vi: string} $payload
     */
    private function sendGrammarMessage(string $chatId, Topic $topic, array $payload, int $lessonId): void
    {
        $grammar = $payload['grammar'];

        $lines = [];
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🧠 <b>CẤU TRÚC CÂU HAY</b>";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "";

        $lines[] = "<code>{$grammar['structure']}</code>";
        $lines[] = "";

        if (! empty($grammar['explanation_vi'])) {
            $lines[] = "💡 <b>Giải thích:</b>";
            $lines[] = $grammar['explanation_vi'];
            $lines[] = "";
        }

        if (! empty($grammar['example_en'])) {
            $lines[] = "✏️ <b>Ví dụ:</b>";
            $lines[] = "<i>\"{$grammar['example_en']}\"</i>";
            $lines[] = "";
        }

        if (! empty($grammar['example_vi'])) {
            $lines[] = "🇻🇳 <b>Dịch:</b>";
            $lines[] = $grammar['example_vi'];
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📝 Quiz với từ vựng này', 'callback_data' => "tgb:q:{$lessonId}"],
                    ['text' => '🔁 Ôn tập SR ngay', 'callback_data' => 'tgb:rv'],
                ],
                [
                    ['text' => '📚 Lộ trình', 'callback_data' => 'tgb:roadmap'],
                    ['text' => '🏠 Menu', 'callback_data' => 'tgb:menu'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, implode("\n", $lines), $keyboard);
    }
}
