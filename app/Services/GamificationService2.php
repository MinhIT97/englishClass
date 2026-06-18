<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\User;
use App\Models\UserQuest;
use Carbon\Carbon;

/**
 * Quest engine — daily/weekly challenges that reward XP on
 * completion. Progress is recorded against named metrics; the
 * application calls trackMetric() from the relevant controllers
 * (lesson completion, flashcard grade, writing submit, etc).
 */
class GamificationService2
{
    /**
     * Increment a user's progress on every active quest whose
     * metric matches $metricName. Auto-completes the quest and
     * awards XP when progress reaches target.
     */
    public function trackMetric(User $user, string $metricName, int $delta = 1): void
    {
        $now = Carbon::now();

        $activeQuests = Quest::query()
            ->where('is_active', true)
            ->where('metric', $metricName)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->get();

        foreach ($activeQuests as $quest) {
            $userQuest = UserQuest::firstOrCreate(
                ['user_id' => $user->id, 'quest_id' => $quest->id],
                ['progress' => 0],
            );

            if ($userQuest->completed_at) {
                continue;
            }

            $userQuest->progress = min($quest->target, $userQuest->progress + $delta);

            if ($userQuest->progress >= $quest->target) {
                $userQuest->completed_at = $now;
                $user->increment('xp', $quest->xp_reward);
            }

            $userQuest->save();
        }
    }

    /**
     * Get the user's active quests with progress, sorted by
     * completion status (incomplete first so they see what to do).
     */
    public function activeQuestsForUser(User $user): array
    {
        $now = Carbon::now();

        $quests = Quest::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->with(['userQuests' => fn ($q) => $q->where('user_id', $user->id)])
            ->get();

        return $quests->map(function (Quest $q) use ($user) {
            $uq = $q->userQuests->first();
            return (object) [
                'id' => $q->id,
                'title' => $q->title,
                'description' => $q->description,
                'icon' => $q->icon ?? '🎯',
                'progress' => $uq?->progress ?? 0,
                'target' => $q->target,
                'xp_reward' => $q->xp_reward,
                'completed' => (bool) $uq?->completed_at,
            ];
        })->sortBy('completed')->values()->all();
    }
}