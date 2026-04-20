<?php

namespace Modules\Gamification\Services;

use App\Models\User;
use Carbon\Carbon;

class GamificationService
{
    /**
     * Award XP to a user and update streak.
     */
    public function awardPoints(User $user, int $points)
    {
        $user->increment('xp', $points);
        $this->updateStreak($user);
    }

    /**
     * Update the user's learning streak.
     */
    protected function updateStreak(User $user)
    {
        $lastActive = $user->last_active_at ? Carbon::parse($user->last_active_at) : null;
        $today = Carbon::today();

        if (!$lastActive) {
            $user->streak = 1;
        } else {
            if ($lastActive->isYesterday()) {
                $user->streak += 1;
            } elseif ($lastActive->isToday()) {
                // Already active today, do nothing to streak
            } else {
                // Streak broken
                $user->streak = 1;
            }
        }

        $user->last_active_at = Carbon::now();
        $user->save();
    }

    /**
     * Get user's current level and progress data.
     */
    public function getLevelData(User $user): array
    {
        $xpPerLevel = 100;
        $totalXp = $user->xp ?? 0;
        
        $level = floor($totalXp / $xpPerLevel) + 1;
        $xpInCurrentLevel = $totalXp % $xpPerLevel;
        $progressPercent = ($xpInCurrentLevel / $xpPerLevel) * 100;
        
        return [
            'level' => $level,
            'current_xp' => $xpInCurrentLevel,
            'xp_to_next' => $xpPerLevel,
            'percent' => $progressPercent
        ];
    }
}
