<?php

namespace Modules\TelegramBot\Services;

use App\Models\LessonRequest;
use App\Models\User;
use App\Services\LessonQuotaService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Models\DailyLesson;
use Modules\TelegramBot\Models\GrammarEntry;
use Modules\TelegramBot\Models\LearningProfile;
use Modules\TelegramBot\Models\ReadingPassage;
use Modules\TelegramBot\Models\ReadingPassageReview;
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
    /**
     * Default daily cap for `/extra` lessons. Now enforced centrally via
     * LessonQuotaService so admin overrides / per-user `lesson_limit`
     * take effect. Kept as a constant for backward compatibility with
     * callers that reference it.
     */
    public const EXTRA_DAILY_LIMIT = 3;

    private ?string $lastFailureReason = null;

    public function __construct(
        private readonly GeminiLessonGenerator $generator,
        private readonly TelegramService $telegram,
        private readonly AchievementService $achievements,
        private readonly LevelService $levels,
        private readonly TextToSpeechService $tts,
        private readonly ReadingPassageService $readingService,
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

        // Repair legacy/onboarded users whose path was created while the
        // topic seed data was missing on the server.
        if (! UserPath::query()->where('user_id', $user->id)->exists()) {
            $topics = Topic::query()
                ->where('purpose', $profile->purpose)
                ->where('is_active', true)
                ->orderBy('order_index')
                ->get();

            if ($topics->isNotEmpty()) {
                DB::transaction(function () use ($user, $topics) {
                    foreach ($topics as $index => $topic) {
                        UserPath::query()->updateOrCreate(
                            [
                                'user_id' => $user->id,
                                'topic_id' => $topic->id,
                            ],
                            [
                                'status' => $index === 0
                                    ? UserPath::STATUS_CURRENT
                                    : UserPath::STATUS_LOCKED,
                                'started_at' => $index === 0 ? Carbon::now() : null,
                            ]
                        );
                    }
                });

                return $topics->first();
            }
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
     *
     * A topic is now complete when:
     *   1. Every vocabulary entry for the topic has a mature schedule
     *      (repetitions >= 2) — same rule as before, AND
     *   2. Every active reading passage attached to the topic has either
     *      been attempted and reached MATURE_REPETITIONS, OR the user
     *      has not enrolled in it (we don't force enrol; we only check
     *      passages the user has touched).
     *
     * Topics with zero vocabulary are still NOT auto-completed — the
     * daily-lesson pipeline always seeds 5 words, so a topic with 0
     * words means it hasn't been taught yet.
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
            ->where('repetitions', '>=', ReviewSchedule::MATURE_REPETITIONS)
            ->count();

        if ($matureWords < $totalWords) {
            return false;
        }

        // Reading-passage check: every passage the user enrolled in for
        // this topic must also be mature. We don't auto-enrol; we only
        // check passages the user already touched.
        $enrolledPassageIds = ReadingPassageReview::query()
            ->forUser($user->id)
            ->whereHas('passage', function ($q) use ($topic) {
                $q->where('topic_id', $topic->id);
            })
            ->pluck('reading_passage_id')
            ->all();

        $maturePassages = ReadingPassageReview::query()
            ->forUser($user->id)
            ->whereIn('reading_passage_id', $enrolledPassageIds ?: [0])
            ->where('repetitions', '>=', ReadingPassageReview::MATURE_REPETITIONS)
            ->count();

        if ($maturePassages < count($enrolledPassageIds)) {
            return false;
        }

        $path->status = UserPath::STATUS_COMPLETED;
        $path->completed_at = Carbon::now();
        $path->save();

        // Trigger topic-completion achievement check.
        try {
            app(AchievementService::class)->checkAndUnlock($user, 'topic_completed');
        } catch (\Throwable $e) {
            Log::warning('[TelegramBot] topic achievement check failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Build + send today's lesson to a single user.
     *
     * @return bool true on successful send
     */
    public function sendDailyLesson(User $user, ?Carbon $when = null, bool $force = false, ?string $lessonType = null): bool
    {
        $this->lastFailureReason = null;
        $when ??= Carbon::now();

        $profile = LearningProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $profile || $profile->is_paused) {
            $this->lastFailureReason = ! $profile ? 'profile_missing' : 'profile_paused';
            return false;
        }

        $link = UserTelegramLink::query()->where('user_id', $user->id)->first();
        if (! $link) {
            $this->lastFailureReason = 'telegram_link_missing';
            return false;
        }

        // Avoid duplicates on the same date (scheduled lessons only).
        $alreadySent = DailyLesson::query()
            ->where('user_id', $user->id)
            ->whereDate('lesson_date', $when->toDateString())
            ->where('is_extra', false)
            ->where('status', DailyLesson::STATUS_SENT)
            ->exists();

        if ($alreadySent && ! $force) {
            $this->lastFailureReason = 'lesson_already_sent';
            return false;
        }

        $topic = $this->getOrAssignCurrentTopic($user, $profile);
        if (! $topic) {
            $hasTopics = Topic::query()
                ->where('purpose', $profile->purpose)
                ->where('is_active', true)
                ->exists();
            $hasPaths = UserPath::query()->where('user_id', $user->id)->exists();
            $this->lastFailureReason = ! $hasTopics
                ? 'no_topics_configured'
                : ($hasPaths ? 'learning_path_completed' : 'learning_path_missing');
            Log::warning('[TelegramBot] No topic available for user', ['user_id' => $user->id]);

            if ($this->lastFailureReason === 'learning_path_completed') {
                $throttleKey = "tgb:topics_completed_msg:{$user->id}";
                if (! Cache::has($throttleKey)) {
                    $this->telegram->sendMessage(
                        $link->telegram_chat_id,
                        "🎉 <b>Chúc mừng! Bạn đã hoàn thành toàn bộ lộ trình!</b>\n\n"
                        . "Bạn có thể:\n"
                        . "• Tiếp tục ôn tập với /review\n"
                        . "• Làm /quiz để củng cố kiến thức\n"
                        . "• Chơi /game để thư giãn\n"
                        . "• Liên hệ admin để được mở lộ trình mới",
                        ['inline_keyboard' => [[
                            ['text' => '🔁 Ôn tập SR', 'callback_data' => 'tgb:rv'],
                            ['text' => '🏠 Menu', 'callback_data' => 'tgb:menu'],
                        ]]]
                    );
                    Cache::put($throttleKey, true, now()->addDays(7));
                }
            }

            return false;
        }

        $isExtra = $force;

        if ($isExtra) {
            $lesson = DailyLesson::query()->create([
                'user_id' => $user->id,
                'lesson_date' => $when->toDateString(),
                'is_extra' => true,
                'topic_id' => $topic->id,
                'status' => DailyLesson::STATUS_SCHEDULED,
            ]);
        } else {
            $lesson = DailyLesson::query()->updateOrCreate(
                ['user_id' => $user->id, 'lesson_date' => $when->toDateString(), 'is_extra' => false],
                ['topic_id' => $topic->id, 'status' => DailyLesson::STATUS_SCHEDULED]
            );
        }

        // Show typing indicator so the user sees the bot is "thinking"
        // during the (typically 3-8s) Gemini call. Telegram expires this
        // after ~5s, so we resend once if the call takes longer.
        $this->telegram->sendChatAction($link->telegram_chat_id, 'typing');
        $payload = $lessonType
            ? $this->generator->generateDailyLessonOfType($profile, $topic, $lessonType, $pathWordCount = 5)
            : $this->generator->generateDailyLesson($profile, $topic, $pathWordCount = 5);
        $this->telegram->sendChatAction($link->telegram_chat_id, 'typing');
        if (! $payload) {
            $this->lastFailureReason = 'gemini_generation_failed';
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
            $grammarEntry = GrammarEntry::query()->updateOrCreate(
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

            // Create grammar SR schedule so grammar patterns are also reviewed.
            \Modules\TelegramBot\Models\GrammarReviewSchedule::query()->firstOrCreate(
                ['user_id' => $user->id, 'grammar_entry_id' => $grammarEntry->id],
                [
                    'ease_factor' => 2.50,
                    'interval_days' => 0,
                    'repetitions' => 0,
                    'next_review_at' => Carbon::now()->addDays(3),
                ]
            );
        });

        // Send 3 separate messages: intro, content (vocab/grammar/reading/conv/listening), recap.
        // Wrapped in try-catch so Telegram send failures are recorded on the lesson row
        // and don't leave the lesson in a phantom SCHEDULED state.
        try {
            $resolvedType = $payload['lesson_type'] ?? GeminiLessonGenerator::TYPE_VOCAB;

            $lastResponse = $this->sendIntroMessage($link->telegram_chat_id, $user, $topic, $payload, $when, $resolvedType);
            if ($lastResponse === null) {
                throw new \RuntimeException('Intro message send failed');
            }

            // Content message(s).
            match ($resolvedType) {
                GeminiLessonGenerator::TYPE_VOCAB => $this->sendVocabAndGrammarMessages(
                    $link->telegram_chat_id, $topic, $payload, $lesson->id
                ),
                GeminiLessonGenerator::TYPE_GRAMMAR => $this->sendVocabAndGrammarMessages(
                    $link->telegram_chat_id, $topic, $payload, $lesson->id
                ),
                GeminiLessonGenerator::TYPE_READING => $this->sendReadingMessage(
                    $link->telegram_chat_id, $user, $topic, $payload, $lesson->id
                ),
                GeminiLessonGenerator::TYPE_CONVERSATION => $this->sendConversationMessage(
                    $link->telegram_chat_id, $payload, $lesson->id
                ),
                GeminiLessonGenerator::TYPE_LISTENING => $this->sendListeningMessage(
                    $link->telegram_chat_id, $payload, $lesson->id
                ),
                GeminiLessonGenerator::TYPE_REVIEW => $this->sendReviewMessage(
                    $link->telegram_chat_id, $user, $payload, $lesson->id
                ),
            };

            // Word of the Day — lightweight bonus message, best-effort.
            try {
                $wotd = $this->generator->generateWordOfTheDay();
                if ($wotd && ! empty($wotd['word'])) {
                    $audioCallback = $this->tts->callbackData($wotd['word']);
                    $audioRow = $audioCallback
                        ? [[['text' => '🔊 Nghe phát âm', 'callback_data' => $audioCallback]]]
                        : [];
                    $this->telegram->sendMessage(
                        $link->telegram_chat_id,
                        "🌟 <b>Word of the Day:</b> <b>{$wotd['word']}</b>\n"
                        . "📐 <i>" . ($wotd['pos'] ?? '') . "</i>\n"
                        . "🇻🇳 " . ($wotd['meaning_vi'] ?? '') . "\n"
                        . "💬 <i>\"" . ($wotd['example_en'] ?? '') . "\"</i>",
                        ! empty($audioRow) ? ['inline_keyboard' => $audioRow] : []
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[TelegramBot] Word of the day send failed', ['error' => $e->getMessage()]);
            }

            $lesson->status = DailyLesson::STATUS_SENT;
            $lesson->telegram_message_id = $lastResponse['result']['message_id'] ?? null;
            $lesson->sent_at = Carbon::now();
            $lesson->save();
        } catch (\Throwable $e) {
            Log::error('[TelegramBot] Telegram send failed after vocab persistence', [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'error' => $e->getMessage(),
            ]);
            $lesson->status = DailyLesson::STATUS_FAILED;
            $lesson->error_message = 'Telegram send failed: ' . \Illuminate\Support\Str::limit($e->getMessage(), 200);
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

        // Capture XP BEFORE streak/achievement XP grants so we can
        // detect level-up after they're applied.
        $xpBefore = $user->xp ?? 0;

        // Streak + XP first so the recap card shows fresh values.
        $this->bumpStreak($user, $when);

        // Recap card (message #4) + achievement check + celebration.
        // We deliberately send the recap BEFORE the celebration so the
        // user sees "today's summary" framing, then the bonus celebration
        // on top.
        $this->sendRecapCard($link->telegram_chat_id, $user, count($payload['vocabulary']), $resolvedType);

        $unlocked = $this->achievements->checkAndUnlock($user, 'lesson_sent', [
            'vocab_count' => VocabularyEntry::query()->where('user_id', $user->id)->count(),
            'streak' => $user->streak,
        ]);
        if (! empty($unlocked)) {
            $this->achievements->celebrate($link->telegram_chat_id, $user, $unlocked);
        }

        // Level-up check (after all XP grants are done). Celebration
        // happens AFTER achievement so the user gets one clean stack:
        // recap → achievement(s) → level up.
        try {
            $levelUp = $this->levels->checkLevelUp($user, $xpBefore);
            if (! empty($levelUp['celebrated'])) {
                $this->levels->celebrate($link->telegram_chat_id, $user, $levelUp);
            }
        } catch (\Throwable $e) {
            Log::warning('[TelegramBot] level check failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    public function lastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    /**
     * Message #4: a short "you did it" recap that gives the user a sense
     * of accomplishment + a clear call-to-action for the next session.
     */
    private function sendRecapCard(string $chatId, User $user, int $wordsLearned, string $lessonType = GeminiLessonGenerator::TYPE_VOCAB): void
    {
        $streak = $user->streak ?? 0;
        $xp = $user->xp ?? 0;
        $levelInfo = $this->levels->currentLevelInfo($user);
        $levelProgress = $this->levels->progressPercent($user);
        $typeLabel = GeminiLessonGenerator::TYPE_LABELS[$lessonType] ?? 'Bài học';

        $streakLine = $streak > 0
            ? "🔥 Streak: <b>{$streak} ngày</b>\n"
            : "💡 Hoàn thành bài học hôm nay để bắt đầu streak!\n";

        // Build a type-specific recap. Non-vocab lessons don't necessarily
        // add words (reading/listening may have 0), so we tailor the line.
        $contentLine = match ($lessonType) {
            GeminiLessonGenerator::TYPE_READING => "  • 📖 Đã đọc hiểu 1 đoạn văn + 3 câu hỏi",
            GeminiLessonGenerator::TYPE_CONVERSATION => "  • 💬 Đã học 1 đoạn hội thoại mẫu",
            GeminiLessonGenerator::TYPE_LISTENING => "  • 🎧 Đã nghe transcript + trả lời 3 câu hỏi",
            GeminiLessonGenerator::TYPE_REVIEW => "  • 🔁 Đã ôn lại 10 từ vựng gần nhất",
            default => "  • 📚 Học thêm: <b>{$wordsLearned} từ vựng</b> mới\n  • 🧠 Cấu trúc câu hay đã ghi nhớ",
        };

        $text = "🎉 <b>Hoàn thành bài học hôm nay!</b>\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "🎓 <b>Loại bài:</b> {$typeLabel}\n\n"
            . "📊 <b>Tóm tắt:</b>\n"
            . $contentLine . "\n"
            . "  • " . trim($streakLine)
            . "  • ⚡ Tổng XP: <b>{$xp}</b>\n"
            . "  • {$levelInfo['emoji']} Level: <b>{$levelInfo['level']} — {$levelInfo['name_vi']}</b> ({$levelProgress}%)\n\n"
            . "💡 <b>Gợi ý tiếp theo:</b>\n"
            . "  • Ôn lại từ vựng hôm nay sau 4-6 giờ\n"
            . "  • Làm /quiz để nhớ lâu hơn\n"
            . "  • Hẹn gặp bạn ngày mai! 🌅\n";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📝 Làm quiz ngay', 'callback_data' => 'tgb:q:start'],
                    ['text' => '🔁 Ôn tập SR', 'callback_data' => 'tgb:rv'],
                ],
                [
                    ['text' => '🏆 Huy hiệu của tôi', 'callback_data' => 'tgb:achievements'],
                    ['text' => '🏠 Menu chính', 'callback_data' => 'tgb:menu'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Send an on-demand extra lesson to a user (triggered by `/extra`
     * command or the "📖 Học thêm bài" menu button).
     *
     * Reuses sendDailyLesson() with $force=true to bypass the per-day
     * duplicate guard. Enforces pre-conditions in order:
     *   1. The user has the `can_request_extra_lesson` flag set on their
     *      user record (admin-controlled).
     *   2. Learning profile exists and isn't paused.
     *   3. Quota check via LessonQuotaService — admins and unlimited
     *      users bypass; everyone else is capped at user.lesson_limit.
     *
     * XP and streak are NOT awarded (bumpStreak is idempotent per day,
     * so re-running it has no effect anyway — but we never call it here).
     *
     * @return array{ok: bool, reason: string}
     */
    public function sendExtraLesson(User $user): array
    {
        if (! $user->can_request_extra_lesson) {
            return ['ok' => false, 'reason' => 'no_permission'];
        }

        $profile = LearningProfile::query()->where('user_id', $user->id)->first();
        if (! $profile) {
            return ['ok' => false, 'reason' => 'not_onboarded'];
        }

        if ($profile->is_paused) {
            return ['ok' => false, 'reason' => 'paused'];
        }

        // Centralized quota check — respects admin role, is_unlimited
        // flag, and per-user lesson_limit (with admin-approved bumps).
        $quotaCheck = app(LessonQuotaService::class)
            ->check($user, LessonRequest::TYPE_DAILY_LESSON);

        if (! $quotaCheck['allowed']) {
            return ['ok' => false, 'reason' => 'daily_limit'];
        }

        $link = UserTelegramLink::query()->where('user_id', $user->id)->first();
        if (! $link) {
            return ['ok' => false, 'reason' => 'no_link'];
        }

        // All validations passed — notify user we're generating.
        $this->telegram->sendMessage(
            $link->telegram_chat_id,
            "📖 <b>Đang tạo bài học mới...</b>\n\n⏳ Vui lòng đợi trong giây lát."
        );

        // Increment counter BEFORE generation so two parallel requests
        // can't both pass the limit check. The finally block guarantees
        // the counter is rolled back on any failure path.
        $this->incrementExtrasToday($user);
        $sent = false;

        try {
            $sent = $this->sendDailyLesson($user, Carbon::now(), force: true, lessonType: GeminiLessonGenerator::TYPE_VOCAB);
        } catch (\Throwable $e) {
            Log::error('[TelegramBot] Extra lesson exception', [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
            $this->telegram->sendAdminAlert('Extra lesson creation crashed', [
                'feature' => 'telegram_extra_lesson',
                'user_id' => $user->id,
                'email' => $user->email,
            ], $e);

            return ['ok' => false, 'reason' => 'send_failed'];
        } finally {
            if (! $sent) {
                $this->decrementExtrasToday($user);
            }
        }

        if (! $sent) {
            $reason = $this->lastFailureReason() ?? 'unknown';

            // Non-recoverable reasons — no admin alert needed.
            if (in_array($reason, ['learning_path_completed', 'no_topics_configured', 'learning_path_missing'], true)) {
                return ['ok' => false, 'reason' => $reason];
            }

            $this->telegram->sendAdminAlert('Extra lesson could not be created', [
                'feature' => 'telegram_extra_lesson',
                'user_id' => $user->id,
                'email' => $user->email,
                'failure_reason' => $reason,
                'hint' => 'Check the preceding Gemini or Telegram alert and laravel.log.',
            ]);
            return ['ok' => false, 'reason' => 'send_failed'];
        }

        return ['ok' => true, 'reason' => 'ok'];
    }

    /**
     * Number of `/extra` lessons the user has already used today.
     */
    private function extrasUsedToday(User $user): int
    {
        $key = $this->extraCountKey($user);
        return (int) cache()->get($key, 0);
    }

    private function incrementExtrasToday(User $user): void
    {
        $key = $this->extraCountKey($user);
        // Initialize with TTL if absent so cache()->increment works on
        // drivers that don't auto-create keys (Redis behaves; array cache
        // is fine). 36h covers any timezone drift safely.
        if (cache()->get($key) === null) {
            cache()->put($key, 0, now()->addHours(36));
        }
        cache()->increment($key);
    }

    private function decrementExtrasToday(User $user): void
    {
        $key = $this->extraCountKey($user);
        if (cache()->get($key) !== null) {
            cache()->decrement($key);
        }
    }

    private function extraCountKey(User $user): string
    {
        return "tgb:extra_count:{$user->id}:" . Carbon::now()->toDateString();
    }

    public function bumpStreak(User $user, ?Carbon $when = null): void
    {
        $when ??= Carbon::now();
        $lock = Cache::lock("tgb:streak_lock:{$user->id}", 5);

        // Serialize concurrent invocations (cron + /extra firing at the
        // same minute) so two parallel calls can't both observe
        // $lastDate === null and double-increment the streak.
        $lock->block(5, function () use ($user, $when) {
            $today = $when->toDateString();
            $lastKey = "tgb:last_lesson:{$user->id}";
            $lastDate = cache()->get($lastKey);
            $usedFreeze = false;
            $freezeEarned = false;

            if ($lastDate === $today) {
                return; // already counted today
            }

            if ($lastDate === $when->copy()->subDay()->toDateString()) {
                $user->streak = ($user->streak ?? 0) + 1;
            } elseif ($lastDate !== null && ($user->streak_freezes ?? 0) > 0) {
                // Streak would break, but user has a freeze token — consume it.
                $user->streak_freezes = ($user->streak_freezes ?? 1) - 1;
                $user->streak = ($user->streak ?? 0) + 1;
                $usedFreeze = true;
            } else {
                $user->streak = 1;
            }

            // Award streak freezes: 1 per 7 consecutive days.
            if (($user->streak % 7) === 0 && ($user->streak_freezes ?? 0) < 5) {
                $user->streak_freezes = ($user->streak_freezes ?? 0) + 1;
                $freezeEarned = true;
            }

            $user->xp = ($user->xp ?? 0) + 10; // daily lesson XP
            $user->save();

            cache()->put($lastKey, $today, now()->addDays(2));
            cache()->put("tgb:last_lesson_cal:{$user->id}:{$today}", true, now()->addDays(8));

            // Send streak freeze notification if one was consumed or earned.
            if ($usedFreeze || $freezeEarned) {
                try {
                    $link = UserTelegramLink::query()->where('user_id', $user->id)->first();
                    if ($link) {
                        $msg = $usedFreeze
                            ? "🧊 <b>Streak freeze đã được dùng!</b> Bạn còn <b>{$user->streak_freezes}</b> freeze."
                            : "🎁 <b>+1 Streak Freeze!</b> Bạn có <b>{$user->streak_freezes}</b> freeze. Học đều 7 ngày để nhận thêm!";
                        app(TelegramService::class)->sendMessage($link->telegram_chat_id, $msg);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[TelegramBot] freeze notification failed', ['user_id' => $user->id]);
                }
            }

            // Trigger streak-based achievement check (best-effort —
            // we don't want to break the streak update if the check fails).
            try {
                app(AchievementService::class)->checkAndUnlock(
                    $user,
                    'streak_changed',
                    ['streak' => $user->streak, 'freeze_used' => $usedFreeze]
                );
            } catch (\Throwable $e) {
                Log::warning('[TelegramBot] achievement check failed in bumpStreak', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Send message 1 of N: greeting + topic intro. The "what's in this
     * lesson" footer is tailored to the lesson type so the user knows
     * what to expect.
     *
     * @param array{vocabulary: list<array<string, string>>, grammar: array<string, string>, topic_intro_vi: string, lesson_type?: string, extra?: array} $payload
     */
    private function sendIntroMessage(
        string $chatId,
        User $user,
        Topic $topic,
        array $payload,
        Carbon $when,
        string $lessonType
    ): ?array
    {
        $hour = (int) $when->format('G');
        $greeting = match (true) {
            $hour < 5 => '🌙 Chào buổi đêm',
            $hour < 11 => '☀️ Chào buổi sáng',
            $hour < 14 => '🌤️ Chào buổi trưa',
            $hour < 18 => '🌅 Chào buổi chiều',
            default => '🌙 Chào buổi tối',
        };

        $typeLabel = GeminiLessonGenerator::TYPE_LABELS[$lessonType] ?? 'Bài học';

        $agenda = match ($lessonType) {
            GeminiLessonGenerator::TYPE_VOCAB,
            GeminiLessonGenerator::TYPE_GRAMMAR
                => "1️⃣ Từ vựng mới\n2️⃣ Cấu trúc câu hay\n3️⃣ Quiz luyện tập",
            GeminiLessonGenerator::TYPE_READING
                => "1️⃣ Đoạn đọc ngắn (60-90 từ)\n2️⃣ 3 câu hỏi hiểu bài\n3️⃣ Từ vựng trong bài để ôn",
            GeminiLessonGenerator::TYPE_CONVERSATION
                => "1️⃣ Đoạn hội thoại mẫu\n2️⃣ Từ vựng nổi bật\n3️⃣ Ngữ cảnh sử dụng",
            GeminiLessonGenerator::TYPE_LISTENING
                => "1️⃣ Transcript ngắn (~80 từ)\n2️⃣ Nút nghe audio\n3️⃣ 3 câu hỏi nghe hiểu",
            GeminiLessonGenerator::TYPE_REVIEW
                => "1️⃣ Từ vựng bạn đã học gần đây\n2️⃣ Bài ôn tập nhanh\n3️⃣ Tiến độ tuần này",
            default => "1️⃣ Nội dung chính\n2️⃣ Luyện tập",
        };

        $streakDays = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $when->copy()->subDays($i)->toDateString();
            $hasLesson = Cache::get("tgb:last_lesson_cal:{$user->id}:{$d}", false);
            if (! $hasLesson && $i === 0) {
                $hasLesson = true; // today's lesson counts
            }
            $streakDays[] = $hasLesson ? '🟢' : '⚪';
        }
        $calendar = implode('', $streakDays);

        $text = "━━━━━━━━━━━━━━━━━━━━\n"
            . "{$greeting}, <b>{$user->name}</b>! 👋\n"
            . "📅 " . $when->format('l, d/m/Y') . "  {$calendar}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "🎓 <b>Hôm nay:</b> {$typeLabel}\n"
            . "📌 <b>Chủ đề:</b> <b>{$topic->name_vi}</b> <i>({$topic->name_en})</i>\n\n"
            . (! empty($payload['topic_intro_vi'])
                ? "💭 <i>{$payload['topic_intro_vi']}</i>\n\n"
                : '')
            . "📦 <b>Bài học gồm:</b>\n"
            . $agenda
            . "\n\n⬇️ <i>Xem bên dưới...</i>";

        return $this->telegram->sendMessage($chatId, $text);
    }

    /**
     * Send vocab + grammar messages (used by vocab/grammar lesson types).
     *
     * @param array{vocabulary: list<array<string, string>>, grammar: array<string, string>, topic_intro_vi: string, lesson_type?: string, extra?: array} $payload
     */
    private function sendVocabAndGrammarMessages(string $chatId, Topic $topic, array $payload, int $lessonId): void
    {
        $this->sendVocabularyMessage($chatId, $topic, $payload);
        $this->sendGrammarMessage($chatId, $topic, $payload, $lessonId);
    }

    /**
     * Send message 2: vocabulary card. Each word now has a small 🔊
     * button next to it that links to the TTS audio URL.
     *
     * @param array{vocabulary: list<array<string, string>>, grammar: array<string, string>, topic_intro_vi: string, lesson_type?: string, extra?: array} $payload
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

        $audioButtons = [];

        foreach ($words as $i => $w) {
            $num = $i + 1;
            $ipa = ! empty($w['ipa']) ? " <code>{$w['ipa']}</code>" : '';
            $pos = ! empty($w['pos']) ? " <i>[{$w['pos']}]</i>" : '';
            $lines[] = "<b>{$num}. {$w['word']}</b>{$pos}{$ipa}";
            if ($i < count($words) - 1) {
                $lines[] = "";
            }

            // Build a TTS button for this word.
            $audioCallback = $this->tts->callbackData((string) ($w['word'] ?? ''));
            if ($audioCallback !== null) {
                $audioButtons[] = [
                    'text' => "🔊 #{$num}",
                    'callback_data' => $audioCallback,
                ];
            }
        }

        $lines[] = "";
        $lines[] = "💡 <i>Bấm 🔊 để nghe phát âm. Thử nhớ nghĩa trước khi bấm \"Xem chi tiết\" nhé!</i>";

        // Build the keyboard: put audio buttons in a single row at the
        // top (max 5 fit on a row). If the lesson has no audio, skip.
        $keyboardRows = [];
        if (! empty($audioButtons)) {
            $keyboardRows[] = $audioButtons;
        }
        $keyboardRows[] = [
            ['text' => '📖 Xem chi tiết từng từ', 'callback_data' => 'tgb:vocab-detail'],
        ];
        $keyboardRows[] = [
            ['text' => '📝 Làm quiz ngay', 'callback_data' => 'tgb:q:start'],
            ['text' => '✍️ Tạo câu với từ này', 'callback_data' => 'tgb:practice:sentence'],
        ];

        $this->telegram->sendMessage($chatId, implode("\n", $lines), ['inline_keyboard' => $keyboardRows]);

        // Lightweight related-word tip — pull one word from today's lesson.
        $firstWord = $words[0]['word'] ?? null;
        if ($firstWord) {
            $this->telegram->sendMessage(
                $chatId,
                "💡 <b>Mẹo nhỏ:</b> Từ <b>{$firstWord}</b> thường đi với các từ như: "
                . "<i>common, useful, practical</i>. Hãy thử đặt câu với collocation nhé! ✍️"
            );
        }
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

        $lines[] = "<code>" . ($grammar['structure'] ?? '') . "</code>";
        $lines[] = "";

        if (! empty($grammar['explanation_vi'])) {
            $lines[] = "💡 <b>Giải thích:</b>";
            $lines[] = $grammar['explanation_vi'];
            $lines[] = "";
        }

        if (! empty($grammar['example_en'])) {
            $lines[] = "✏️ <b>Ví dụ:</b>";
            $lines[] = "<i>\"" . $grammar['example_en'] . "\"</i>";
            $lines[] = "";
        }

        if (! empty($grammar['example_vi'])) {
            $lines[] = "🇻🇳 <b>Dịch:</b>";
            $lines[] = $grammar['example_vi'];
        }

        // Surface any extra grammar examples that the grammar-focused
        // prompt produces (Tue/Thu).
        if (! empty($grammar['examples_extra_en']) && is_array($grammar['examples_extra_en'])) {
            $lines[] = "";
            $lines[] = "✏️ <b>Thêm ví dụ:</b>";
            foreach ($grammar['examples_extra_en'] as $ex) {
                $lines[] = "  • <i>\"" . $ex . "\"</i>";
            }
        }

        // Build a TTS button for the primary example so the user can
        // hear the grammar pattern spoken.
        $keyboardRows = [];
        if (! empty($grammar['example_en'])) {
            $audioCallback = $this->tts->callbackData((string) $grammar['example_en']);
            if ($audioCallback !== null) {
                $keyboardRows[] = [
                    ['text' => '🔊 Nghe ví dụ', 'callback_data' => $audioCallback],
                ];
            }
        }

        $keyboardRows[] = [
            ['text' => '📝 Quiz với từ vựng này', 'callback_data' => "tgb:q:{$lessonId}"],
            ['text' => '🔁 Ôn tập SR ngay', 'callback_data' => 'tgb:rv'],
        ];
        $keyboardRows[] = [
            ['text' => '📚 Lộ trình', 'callback_data' => 'tgb:roadmap'],
            ['text' => '🏠 Menu', 'callback_data' => 'tgb:menu'],
        ];

        $this->telegram->sendMessage($chatId, implode("\n", $lines), ['inline_keyboard' => $keyboardRows]);
    }

    /**
     * Reading lesson — one message with passage + 3 comprehension
     * questions (answers hidden by spoiler / call-to-action).
     *
     * If the user has a real reading passage on file for this topic, the
     * "Ôn luyện đọc hiểu" button deep-links straight to it (callback
     * `tgb:rp:<passageId>`). Otherwise it falls back to the queue-based
     * `tgb:reading-review` callback which picks whatever's due next.
     * Without this distinction, the user might tap "practice" and end
     * up on a passage about a completely different topic.
     *
     * @param array{extra?: array{passage_en?: string, passage_vi?: string, questions?: list<array{q_en:string, q_vi:string, answer:string}>}} $payload
     */
    private function sendReadingMessage(string $chatId, User $user, Topic $topic, array $payload, int $lessonId): void
    {
        $extra = $payload['extra'] ?? [];
        $passage = $extra['passage_en'] ?? '';
        $passageVi = $extra['passage_vi'] ?? '';
        $questions = $extra['questions'] ?? [];

        $lines = [];
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "📖 <b>ĐỌC HIỂU</b>";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "";
        $lines[] = $passage;
        if (! empty($passageVi)) {
            $lines[] = "";
            $lines[] = "🇻🇳 <i>{$passageVi}</i>";
        }

        $lines[] = "";
        $lines[] = "❓ <b>Câu hỏi:</b>";
        foreach ($questions as $i => $q) {
            $num = $i + 1;
            $lines[] = "  <b>{$num}.</b> " . ($q['q_en'] ?? '');
            if (! empty($q['q_vi'])) {
                $lines[] = "      <i>" . $q['q_vi'] . "</i>";
            }
            if (! empty($q['answer'])) {
                $lines[] = "      💡 <b>Đáp án:</b> <code>" . $q['answer'] . "</code>";
            }
        }

        // Deep-link the practice button to a passage that matches the
        // current topic. We don't auto-enrol here; tgb:rp:<id> will
        // enrol on first click.
        $linkedPassage = ReadingPassage::query()
            ->active()
            ->where('topic_id', $topic->id)
            ->inRandomOrder()
            ->first();

        $practiceCallback = $linkedPassage
            ? "tgb:rp:{$linkedPassage->id}"
            : 'tgb:reading-review';

        $this->telegram->sendMessage(
            $chatId,
            implode("\n", $lines),
            [
                'inline_keyboard' => [
                    [
                        ['text' => '📚 Ôn luyện đọc hiểu', 'callback_data' => $practiceCallback],
                        ['text' => '🔁 Ôn tập SR', 'callback_data' => 'tgb:rv'],
                    ],
                    [
                        ['text' => '📝 Làm quiz', 'callback_data' => 'tgb:q:start'],
                        ['text' => '🏠 Menu', 'callback_data' => 'tgb:menu'],
                    ],
                ],
            ]
        );
    }

    /**
     * Conversation lesson — one message showing the dialog + a small
     * vocab recap.
     *
     * @param array{extra?: array{scenario_vi?: string, lines?: list<array{speaker:string, en:string, vi:string}>}, vocabulary?: list<array<string,string>>} $payload
     */
    private function sendConversationMessage(string $chatId, array $payload, int $lessonId): void
    {
        $extra = $payload['extra'] ?? [];
        $scenario = $extra['scenario_vi'] ?? '';
        $lines = $extra['lines'] ?? [];

        $text = [];
        $text[] = "━━━━━━━━━━━━━━━━━━━━";
        $text[] = "💬 <b>HỘI THOẠI MẪU</b>";
        $text[] = "━━━━━━━━━━━━━━━━━━━━";
        $text[] = "";
        if (! empty($scenario)) {
            $text[] = "🎬 <i>{$scenario}</i>";
            $text[] = "";
        }

        foreach ($lines as $line) {
            $speaker = $line['speaker'] ?? '?';
            $en = $line['en'] ?? '';
            $vi = $line['vi'] ?? '';
            $text[] = "<b>{$speaker}:</b> {$en}";
            if (! empty($vi)) {
                $text[] = "       <i>↳ {$vi}</i>";
            }
        }

        // Pull TTS URL for the first line so the user can hear the
        // opening sentence of the dialog (helps with pronunciation).
        $audioButtons = [];
        if (! empty($lines[0]['en'] ?? null)) {
            $audioCallback = $this->tts->callbackData((string) $lines[0]['en']);
            if ($audioCallback !== null) {
                $audioButtons[] = [
                    'text' => '🔊 Nghe câu đầu',
                    'callback_data' => $audioCallback,
                ];
            }
        }

        $keyboardRows = [];
        if (! empty($audioButtons)) {
            $keyboardRows[] = $audioButtons;
        }
        $keyboardRows[] = [
            ['text' => '📚 Từ vựng trong đoạn', 'callback_data' => 'tgb:vocab-detail'],
            ['text' => '📝 Làm quiz', 'callback_data' => 'tgb:q:start'],
        ];
        $keyboardRows[] = [
            ['text' => '🏠 Menu', 'callback_data' => 'tgb:menu'],
        ];

        $this->telegram->sendMessage($chatId, implode("\n", $text), ['inline_keyboard' => $keyboardRows]);
    }

    /**
     * Listening lesson — transcript + audio play button + comprehension Qs.
     *
     * @param array{extra?: array{transcript_en?: string, transcript_vi?: string, questions?: list<array{q_en:string, q_vi:string, answer:string}>}} $payload
     */
    private function sendListeningMessage(string $chatId, array $payload, int $lessonId): void
    {
        $extra = $payload['extra'] ?? [];
        $transcript = $extra['transcript_en'] ?? '';
        $transcriptVi = $extra['transcript_vi'] ?? '';
        $questions = $extra['questions'] ?? [];

        $text = [];
        $text[] = "━━━━━━━━━━━━━━━━━━━━";
        $text[] = "🎧 <b>NGHE HIỂU</b>";
        $text[] = "━━━━━━━━━━━━━━━━━━━━";
        $text[] = "";
        $text[] = "📜 <b>Transcript:</b>";
        $text[] = "";
        $text[] = "<i>{$transcript}</i>";
        if (! empty($transcriptVi)) {
            $text[] = "";
            $text[] = "🇻🇳 <b>Bản dịch:</b>";
            $text[] = "<i>{$transcriptVi}</i>";
        }

        $text[] = "";
        $text[] = "❓ <b>Câu hỏi:</b>";
        foreach ($questions as $i => $q) {
            $num = $i + 1;
            $text[] = "  <b>{$num}.</b> " . ($q['q_en'] ?? '');
            if (! empty($q['q_vi'])) {
                $text[] = "      <i>" . $q['q_vi'] . "</i>";
            }
            if (! empty($q['answer'])) {
                $text[] = "      💡 <b>Đáp án:</b> <code>" . $q['answer'] . "</code>";
            }
        }

        // Audio button at the top: open the transcript in TTS.
        $audioCallback = $this->tts->callbackData((string) $transcript);
        $keyboardRows = [];
        if ($audioCallback !== null) {
            $keyboardRows[] = [
                ['text' => '🎧 Nghe transcript', 'callback_data' => $audioCallback],
            ];
        }
        $keyboardRows[] = [
            ['text' => '📝 Làm quiz', 'callback_data' => 'tgb:q:start'],
            ['text' => '🏠 Menu', 'callback_data' => 'tgb:menu'],
        ];

        $this->telegram->sendMessage($chatId, implode("\n", $text), ['inline_keyboard' => $keyboardRows]);
    }

    /**
     * Review (CN) lesson — recap the user's recent vocabulary + streak
     * + level progress. No Gemini call; pulls from user data.
     *
     * @param array{topic_intro_vi?: string, extra?: array} $payload
     */
    private function sendReviewMessage(string $chatId, User $user, array $payload, int $lessonId): void
    {
        $recentWords = VocabularyEntry::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $lines = [];
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔁 <b>ÔN TẬP TUẦN</b>";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "";
        $lines[] = $payload['topic_intro_vi'] ?? 'Hãy cùng nhìn lại những gì bạn đã học tuần này!';
        $lines[] = "";

        if ($recentWords->isEmpty()) {
            $lines[] = "📭 Bạn chưa có từ vựng nào trong tuần này. Bài học tiếp theo sẽ tới vào ngày mai!";
        } else {
            $lines[] = "📚 <b>10 từ gần nhất bạn đã học:</b>";
            foreach ($recentWords as $i => $w) {
                $num = $i + 1;
                $ipa = $w->ipa ? " <code>{$w->ipa}</code>" : '';
                $lines[] = "  <b>{$num}. {$w->word}</b>{$ipa} — {$w->meaning_vi}";
            }
        }

        // Streak + XP recap.
        $lines[] = "";
        $streak = $user->streak ?? 0;
        $xp = $user->xp ?? 0;
        $levelInfo = $this->levels->currentLevelInfo($user);
        $levelProgress = $this->levels->progressPercent($user);

        // Weekly stats.
        $weekStart = Carbon::now()->startOfWeek();
        $wordsThisWeek = VocabularyEntry::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $weekStart)
            ->count();
        $reviewsThisWeek = (int) DB::table('tgb_review_schedules')
            ->where('user_id', $user->id)
            ->where('last_reviewed_at', '>=', $weekStart)
            ->count();
        $quizAccuracy = (int) round(
            \Modules\TelegramBot\Models\QuizAttempt::query()
                ->where('user_id', $user->id)
                ->where('attempted_at', '>=', $weekStart)
                ->where('is_correct', true)
                ->count()
            / max(1, \Modules\TelegramBot\Models\QuizAttempt::query()
                ->where('user_id', $user->id)
                ->where('attempted_at', '>=', $weekStart)
                ->count()) * 100
        );

        $lines[] = "📊 <b>Tổng kết tuần này:</b>";
        $lines[] = "  • 🔥 Streak: <b>{$streak} ngày</b>";
        $lines[] = "  • 📚 Từ mới: <b>{$wordsThisWeek}</b>";
        $lines[] = "  • 🔁 Đã ôn: <b>{$reviewsThisWeek} thẻ</b>";
        $lines[] = "  • 🎯 Quiz accuracy: <b>{$quizAccuracy}%</b>";
        $lines[] = "  • {$levelInfo['emoji']} Level: <b>{$levelInfo['level']} — {$levelInfo['name_vi']}</b> ({$levelProgress}%)";
        $lines[] = "  • ⚡ Tổng XP: <b>{$xp}</b>";

        // Mini practice CTA.
        $lines[] = "";
        $lines[] = "💡 <i>Bấm nút bên dưới để ôn lại các thẻ SR đang đến hạn — đó là những từ bạn sắp quên!</i>";

        $this->telegram->sendMessage(
            $chatId,
            implode("\n", $lines),
            [
                'inline_keyboard' => [
                    [
                        ['text' => '🔁 Ôn tập SR ngay', 'callback_data' => 'tgb:rv'],
                        ['text' => '📝 Làm quiz', 'callback_data' => 'tgb:q:start'],
                    ],
                    [
                        ['text' => '🏠 Menu', 'callback_data' => 'tgb:menu'],
                    ],
                ],
            ]
        );
    }
}
