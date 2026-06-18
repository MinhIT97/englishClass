<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Models\QuizAttempt;
use Modules\TelegramBot\Models\UserAchievement;
use Modules\TelegramBot\Models\VocabularyEntry;

/**
 * Awards achievement badges to users and emits celebration messages.
 *
 * Achievements are stored in `tgb_user_achievements` with a unique
 * constraint on (user_id, achievement_key) so unlock is naturally
 * idempotent — calling checkAndUnlock() multiple times for the same
 * trigger will only ever create one row.
 *
 * XP rewards are added to user.xp inside the same transaction as the
 * insert, so a crashed transaction leaves no half-state.
 */
class AchievementService
{
    public const KEY_FIRST_LESSON = 'first_lesson';
    public const KEY_FIRST_QUIZ = 'first_quiz';
    public const KEY_PERFECT_QUIZ = 'perfect_quiz';
    public const KEY_STREAK_3 = 'streak_3';
    public const KEY_STREAK_7 = 'streak_7';
    public const KEY_STREAK_30 = 'streak_30';
    public const KEY_WORDS_50 = 'words_50';
    public const KEY_WORDS_100 = 'words_100';
    public const KEY_WORDS_500 = 'words_500';
    public const KEY_REVIEW_MASTER = 'review_master';

    /**
     * Catalog of every achievement currently defined.
     *
     * Adding a new achievement = adding one entry here + (optionally)
     * adding a check rule in checkAndUnlock(). The display order is the
     * iteration order of this array.
     */
    public const CATALOG = [
        self::KEY_FIRST_LESSON => [
            'name' => 'Bài học đầu tiên',
            'emoji' => '🎓',
            'description' => 'Hoàn thành bài học đầu tiên trên bot.',
            'xp' => 20,
        ],
        self::KEY_FIRST_QUIZ => [
            'name' => 'Quiz hoàn hảo đầu tiên',
            'emoji' => '📝',
            'description' => 'Đạt điểm tuyệt đối 5/5 trong quiz đầu tiên.',
            'xp' => 15,
        ],
        self::KEY_PERFECT_QUIZ => [
            'name' => 'Quiz hoàn hảo',
            'emoji' => '🌟',
            'description' => 'Đạt điểm tuyệt đối trong một bài quiz.',
            'xp' => 20,
        ],
        self::KEY_STREAK_3 => [
            'name' => 'Streak 3 ngày',
            'emoji' => '🔥',
            'description' => 'Học liên tục 3 ngày.',
            'xp' => 30,
        ],
        self::KEY_STREAK_7 => [
            'name' => 'Streak 7 ngày',
            'emoji' => '🔥',
            'description' => 'Học liên tục 7 ngày — một tuần kỷ luật!',
            'xp' => 50,
        ],
        self::KEY_STREAK_30 => [
            'name' => 'Streak 30 ngày',
            'emoji' => '🏆',
            'description' => 'Học liên tục 30 ngày — bạn là huyền thoại!',
            'xp' => 200,
        ],
        self::KEY_WORDS_50 => [
            'name' => '50 từ vựng',
            'emoji' => '📚',
            'description' => 'Tích lũy 50 từ vựng.',
            'xp' => 30,
        ],
        self::KEY_WORDS_100 => [
            'name' => '100 từ vựng',
            'emoji' => '📚',
            'description' => 'Tích lũy 100 từ vựng.',
            'xp' => 50,
        ],
        self::KEY_WORDS_500 => [
            'name' => '500 từ vựng',
            'emoji' => '🏆',
            'description' => 'Tích lũy 500 từ vựng — kho từ vựng phong phú!',
            'xp' => 200,
        ],
        self::KEY_REVIEW_MASTER => [
            'name' => 'Ôn tập thành thạo',
            'emoji' => '🎯',
            'description' => 'Hoàn thành 10 phiên ôn tập SR.',
            'xp' => 50,
        ],
    ];

    public function __construct(
        private readonly TelegramService $telegram,
    ) {
    }

    /**
     * Check relevant achievements for a trigger and unlock any that just
     * became reachable. Returns the list of newly-unlocked keys.
     *
     * @param  string  $trigger   one of: lesson_sent, quiz_finished, streak_changed, review_finished
     * @param  array   $context   optional hints: ['streak' => int, 'vocab_count' => int, 'perfect' => bool]
     * @return string[]           list of unlocked achievement keys
     */
    public function checkAndUnlock(User $user, string $trigger, array $context = []): array
    {
        $candidates = $this->candidatesForTrigger($trigger);
        $unlocked = [];

        foreach ($candidates as $key) {
            if ($this->isUnlocked($user, $key)) {
                continue;
            }

            $xp = $this->evaluate($key, $user, $context);
            if ($xp === false) {
                continue;
            }

            // Insert + XP grant in one transaction so we never have a
            // half-state where the badge exists but XP wasn't awarded.
            try {
                DB::transaction(function () use ($user, $key, $xp) {
                    UserAchievement::query()->create([
                        'user_id' => $user->id,
                        'achievement_key' => $key,
                        'unlocked_at' => Carbon::now(),
                    ]);
                    $user->xp = ($user->xp ?? 0) + $xp;
                    $user->save();
                });
                $unlocked[] = $key;
                Log::info('[TelegramBot] achievement unlocked', [
                    'user_id' => $user->id,
                    'achievement_key' => $key,
                    'xp_awarded' => $xp,
                ]);
            } catch (\Throwable $e) {
                // Unique constraint race: another concurrent call won the
                // insert. That's fine — the unlock is recorded, we just
                // don't double-grant XP.
                Log::info('[TelegramBot] achievement already unlocked (race)', [
                    'user_id' => $user->id,
                    'achievement_key' => $key,
                ]);
            }
        }

        return $unlocked;
    }

    /**
     * Send a celebration message(s) for the given list of newly-unlocked
     * keys. Multiple unlocks are bundled into a single message.
     */
    public function celebrate(string $chatId, User $user, array $unlockedKeys): void
    {
        if (empty($unlockedKeys)) {
            return;
        }

        $totalXp = 0;
        $lines = [];

        foreach ($unlockedKeys as $key) {
            if (! isset(self::CATALOG[$key])) {
                continue;
            }
            $info = self::CATALOG[$key];
            $totalXp += $info['xp'];
            $lines[] = "{$info['emoji']} <b>{$info['name']}</b>  →  +{$info['xp']} XP";
        }

        $header = count($unlockedKeys) === 1
            ? "🏆 <b>THÀNH TỰU MỚI!</b>"
            : "🏆 <b>BẠN VỪA MỞ KHÓA " . count($unlockedKeys) . " THÀNH TỰU!</b>";

        $text = "{$header}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . implode("\n", $lines)
            . "\n\n⚡ <b>Tổng cộng: +{$totalXp} XP</b>\n\n"
            . "<i>Mỗi thành tựu là một bước tiến — tiếp tục nhé! 💪</i>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🏆 Xem tất cả huy hiệu', 'callback_data' => 'tgb:achievements'],
                    ['text' => '🏠 Menu chính', 'callback_data' => 'tgb:menu'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Total number of achievements the user has unlocked so far.
     */
    public function unlockedCount(User $user): int
    {
        return UserAchievement::query()->where('user_id', $user->id)->count();
    }

    /**
     * Whether the user has already unlocked this achievement.
     */
    public function isUnlocked(User $user, string $key): bool
    {
        return UserAchievement::query()
            ->where('user_id', $user->id)
            ->where('achievement_key', $key)
            ->exists();
    }

    // ---------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------

    /**
     * Achievement keys worth checking for a given trigger. Cheap pre-filter
     * so we don't evaluate every badge every time.
     */
    private function candidatesForTrigger(string $trigger): array
    {
        return match ($trigger) {
            'lesson_sent' => [
                self::KEY_FIRST_LESSON,
                self::KEY_STREAK_3,
                self::KEY_STREAK_7,
                self::KEY_STREAK_30,
                self::KEY_WORDS_50,
                self::KEY_WORDS_100,
                self::KEY_WORDS_500,
            ],
            'quiz_finished' => [
                self::KEY_FIRST_QUIZ,
                self::KEY_PERFECT_QUIZ,
            ],
            'streak_changed' => [
                self::KEY_STREAK_3,
                self::KEY_STREAK_7,
                self::KEY_STREAK_30,
            ],
            'review_finished' => [
                self::KEY_REVIEW_MASTER,
            ],
            default => [],
        };
    }

    /**
     * Evaluate whether a given achievement should be unlocked right now.
     *
     * Returns:
     *   - int  → achievement unlocked, this is the XP reward
     *   - false → condition not met, don't unlock
     */
    private function evaluate(string $key, User $user, array $context)
    {
        $xp = self::CATALOG[$key]['xp'] ?? 0;

        switch ($key) {
            case self::KEY_FIRST_LESSON:
                // The trigger 'lesson_sent' itself proves the user has
                // received at least one lesson, so always unlock here.
                return $xp;

            case self::KEY_FIRST_QUIZ:
                // Unlock the first time they nail a perfect score. We
                // treat any perfect quiz as the first one.
                if (! empty($context['perfect'])) {
                    return $xp;
                }
                return false;

            case self::KEY_PERFECT_QUIZ:
                if (! empty($context['perfect'])) {
                    return $xp;
                }
                return false;

            case self::KEY_STREAK_3:
            case self::KEY_STREAK_7:
            case self::KEY_STREAK_30:
                $required = match ($key) {
                    self::KEY_STREAK_3 => 3,
                    self::KEY_STREAK_7 => 7,
                    self::KEY_STREAK_30 => 30,
                };
                $streak = $context['streak'] ?? ($user->streak ?? 0);
                return $streak >= $required ? $xp : false;

            case self::KEY_WORDS_50:
            case self::KEY_WORDS_100:
            case self::KEY_WORDS_500:
                $required = match ($key) {
                    self::KEY_WORDS_50 => 50,
                    self::KEY_WORDS_100 => 100,
                    self::KEY_WORDS_500 => 500,
                };
                $count = $context['vocab_count']
                    ?? VocabularyEntry::query()->where('user_id', $user->id)->count();
                return $count >= $required ? $xp : false;

            case self::KEY_REVIEW_MASTER:
                // Count distinct calendar days on which the user finished
                // a review session. We approximate this by counting review
                // grade events, but cap at the configured max for the day
                // so a single marathon day doesn't grant the badge.
                $days = $this->reviewDaysCompleted($user);
                return $days >= 10 ? $xp : false;

            default:
                return false;
        }
    }

    /**
     * Number of distinct days on which the user has graded at least one
     * SR card. We use the existence of QuizAttempt-style review events;
     * here we approximate by counting ReviewSchedule rows whose
     * last_reviewed_at has been set on distinct dates.
     *
     * (Cheap enough as a daily check — runs at most once per lesson/quiz.)
     */
    private function reviewDaysCompleted(User $user): int
    {
        return (int) DB::table('tgb_review_schedules')
            ->where('user_id', $user->id)
            ->whereNotNull('last_reviewed_at')
            ->select(DB::raw('COUNT(DISTINCT DATE(last_reviewed_at)) as days'))
            ->value('days');
    }
}