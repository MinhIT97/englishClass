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
                "⚠️ Bài hôm nay chưa sẵn sàng. Bạn có thể thử lại sau với /vocab nhé!"
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

        $text = $this->formatLessonMessage($user, $topic, $payload);
        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '📖 Xem chi tiết', 'callback_data' => "tgb:v:{$lesson->id}"],
                    ['text' => '📝 Làm bài quiz', 'callback_data' => "tgb:q:{$lesson->id}"],
                ],
                [
                    ['text' => '📚 Lộ trình', 'callback_data' => 'tgb:roadmap'],
                    ['text' => '⚙️ Cài đặt', 'callback_data' => 'tgb:settings'],
                ],
            ],
        ];

        $response = $this->telegram->sendMessage($link->telegram_chat_id, $text, $replyMarkup);

        if ($response === null) {
            $lesson->status = DailyLesson::STATUS_FAILED;
            $lesson->error_message = 'Telegram send failed';
            $lesson->save();
            return false;
        }

        $lesson->status = DailyLesson::STATUS_SENT;
        $lesson->telegram_message_id = $response['result']['message_id'] ?? null;
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
     * @param array{vocabulary: list<array<string, string>>, grammar: array<string, string>, topic_intro_vi: string} $payload
     */
    private function formatLessonMessage(User $user, Topic $topic, array $payload): string
    {
        $lines = [];
        $lines[] = "🌅 <b>Chào buổi sáng, {$user->name}!</b>";
        $lines[] = "📅 " . Carbon::now()->format('d/m/Y');
        $lines[] = '';
        $lines[] = "📌 <b>Chủ đề hôm nay:</b> {$topic->name_vi} <i>({$topic->name_en})</i>";

        if (! empty($payload['topic_intro_vi'])) {
            $lines[] = "<i>{$payload['topic_intro_vi']}</i>";
        }
        $lines[] = '';

        $lines[] = "📚 <b>Từ vựng mới (" . count($payload['vocabulary']) . " từ):</b>";
        foreach (array_slice($payload['vocabulary'], 0, 5) as $i => $w) {
            $num = $i + 1;
            $ipa = ! empty($w['ipa']) ? " <code>{$w['ipa']}</code>" : '';
            $lines[] = "{$num}. <b>{$w['word']}</b>{$ipa} — <i>{$w['meaning_vi']}</i>";
        }

        $lines[] = '';
        $grammar = $payload['grammar'];
        $lines[] = "🧠 <b>Cấu trúc câu:</b>";
        $lines[] = "<code>{$grammar['structure']}</code>";
        if (! empty($grammar['explanation_vi'])) {
            $lines[] = "💡 {$grammar['explanation_vi']}";
        }
        if (! empty($grammar['example_en'])) {
            $lines[] = "✏️ <i>{$grammar['example_en']}</i>";
        }
        if (! empty($grammar['example_vi'])) {
            $lines[] = "🇻🇳 {$grammar['example_vi']}";
        }
        $lines[] = '';
        $lines[] = "⚡ +10 XP | Gõ /review để ôn tập nhé!";

        return implode("\n", $lines);
    }
}
