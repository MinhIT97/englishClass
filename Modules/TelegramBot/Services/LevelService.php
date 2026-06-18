<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * XP-gated level system. Each user earns XP from lessons, quizzes,
 * achievements. As XP crosses a threshold they "level up" and receive
 * a celebration message + a one-time XP bonus.
 *
 * Levels (cố định, dễ nhớ):
 *   Lv 1 Newcomer  0    – 99   XP   🌱
 *   Lv 2 Explorer  100  – 299  XP   🧭
 *   Lv 3 Scholar   300  – 699  XP   📖
 *   Lv 4 Expert    700  – 1499 XP   🎓
 *   Lv 5 Master    1500 – 2999 XP   🏅
 *   Lv 6 Legend    3000+        XP   👑
 *
 * Bonus XP on level-up is 50 (awarded once per level crossing).
 * The user's "current level" is derived live from XP — no separate
 * column to keep in sync.
 */
class LevelService
{
    /**
     * Ordered list of level thresholds. Index = level (0-based).
     * min_xp is inclusive; max_xp is exclusive (last level has no max).
     */
    public const LEVELS = [
        // index, name (vi), emoji, min_xp, max_xp (or null for last)
        ['level' => 1, 'name' => 'Newcomer',   'name_vi' => 'Người mới',  'emoji' => '🌱', 'min' => 0,    'max' => 100,  'bonus_xp' => 0],
        ['level' => 2, 'name' => 'Explorer',   'name_vi' => 'Nhà khám phá', 'emoji' => '🧭', 'min' => 100,  'max' => 300,  'bonus_xp' => 50],
        ['level' => 3, 'name' => 'Scholar',    'name_vi' => 'Học giả',    'emoji' => '📖', 'min' => 300,  'max' => 700,  'bonus_xp' => 50],
        ['level' => 4, 'name' => 'Expert',     'name_vi' => 'Chuyên gia', 'emoji' => '🎓', 'min' => 700,  'max' => 1500, 'bonus_xp' => 50],
        ['level' => 5, 'name' => 'Master',     'name_vi' => 'Bậc thầy',   'emoji' => '🏅', 'min' => 1500, 'max' => 3000, 'bonus_xp' => 50],
        ['level' => 6, 'name' => 'Legend',     'name_vi' => 'Huyền thoại', 'emoji' => '👑', 'min' => 3000, 'max' => null, 'bonus_xp' => 50],
    ];

    /**
     * Detect and apply any level-up that just happened because of an XP
     * change. Returns the new level (1-based). If no level-up, returns
     * the current level without sending a celebration.
     *
     * Throttling: a level-up celebration for the same user+level is sent
     * at most once. We persist the "last celebrated level" in a cache
     * key so re-runs (e.g. cron re-running) don't double-celebrate.
     *
     * NOTE: this method does NOT itself award the bonus XP — caller is
     * expected to add the bonus XP via $user->xp = ... and re-call if
     * needed. We just announce.
     */
    public function checkLevelUp(User $user, ?int $previousXp = null): ?array
    {
        $xp = $user->xp ?? 0;
        if ($previousXp === null) {
            // First time: just return current level, don't celebrate.
            $current = $this->levelForXp($xp);
            return ['level' => $current, 'celebrated' => false];
        }

        $oldLevel = $this->levelForXp($previousXp);
        $newLevel = $this->levelForXp($xp);

        if ($newLevel <= $oldLevel) {
            return ['level' => $newLevel, 'celebrated' => false];
        }

        // The user crossed at least one level. Celebrate the highest one
        // (one message is enough — sending 3 "you leveled up" messages
        // in a row is noisy).
        $celebrationKey = "tgb:last_level_celebrated:{$user->id}";
        $lastCelebrated = (int) cache()->get($celebrationKey, 0);

        if ($lastCelebrated >= $newLevel) {
            // Already celebrated for this level (race or repeat run).
            return ['level' => $newLevel, 'celebrated' => false];
        }

        $info = self::LEVELS[$newLevel - 1];

        // Award bonus XP and bump the user in one save.
        $user->xp = ($user->xp ?? 0) + ($info['bonus_xp'] ?? 0);
        $user->save();

        cache()->put($celebrationKey, $newLevel, now()->addDays(365));

        Log::info('[TelegramBot] level up', [
            'user_id' => $user->id,
            'from' => $oldLevel,
            'to' => $newLevel,
            'bonus_xp' => $info['bonus_xp'],
        ]);

        return [
            'level' => $newLevel,
            'celebrated' => true,
            'previous_level' => $oldLevel,
            'info' => $info,
        ];
    }

    /**
     * Send the level-up celebration message to the user.
     */
    public function celebrate(string $chatId, User $user, array $levelUp): void
    {
        $info = $levelUp['info'];
        $newLevel = $levelUp['level'];
        $oldLevel = $levelUp['previous_level'];

        $text = "🎉 <b>LEVEL UP!</b>\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "{$info['emoji']} Bạn vừa đạt <b>Level {$newLevel} — {$info['name_vi']}</b>!\n\n"
            . "📈 Hành trình: Level {$oldLevel} → <b>Level {$newLevel}</b>\n"
            . "⚡ Thưởng lên level: <b>+" . ($info['bonus_xp'] ?? 0) . " XP</b>\n"
            . "⚡ Tổng XP: <b>" . ($user->xp ?? 0) . "</b>\n\n"
            . "💪 <i>Mỗi bước nhỏ đều đếm — tiếp tục cháy nhé!</i>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🏆 Xem huy hiệu', 'callback_data' => 'tgb:achievements'],
                    ['text' => '🏠 Menu chính', 'callback_data' => 'tgb:menu'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * 1-based level number for a given XP amount.
     */
    public function levelForXp(int $xp): int
    {
        foreach (self::LEVELS as $idx => $info) {
            if ($xp >= $info['min'] && ($info['max'] === null || $xp < $info['max'])) {
                return $info['level'];
            }
        }
        return 1;
    }

    /**
     * Info array (name, emoji, min, max, bonus_xp) for the user's current
     * level. Returns the topmost level info if XP is huge.
     */
    public function currentLevelInfo(User $user): array
    {
        return self::LEVELS[$this->levelForXp($user->xp ?? 0) - 1];
    }

    /**
     * Progress (0–100) to the next level. Returns 100 when at top level.
     */
    public function progressPercent(User $user): int
    {
        $xp = $user->xp ?? 0;
        $current = $this->levelForXp($xp);
        if ($current >= count(self::LEVELS)) {
            return 100;
        }
        $info = self::LEVELS[$current - 1];
        $next = self::LEVELS[$current];
        $span = max(1, $next['min'] - $info['min']);
        $into = $xp - $info['min'];
        return (int) min(100, round(($into / $span) * 100));
    }
}