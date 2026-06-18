<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-student analytics for the progress dashboard:
 *  - skill radar (4 axes)
 *  - estimated band score
 *  - last 30 days activity heatmap
 *  - weakness breakdown by topic
 */
class ProgressAnalyticsService
{
    public function build(User $user): array
    {
        return [
            'skills' => $this->skillBreakdown($user),
            'estimated_band' => $this->estimatedBand($user),
            'activity' => $this->activityHeatmap($user),
            'weaknesses' => $this->weaknessBreakdown($user),
            'stats' => $this->highLevelStats($user),
        ];
    }

    private function skillBreakdown(User $user): array
    {
        // Accuracy per skill (listening/reading/writing/speaking) over the
        // last 30 days. The actual table depends on the Question module
        // but we expose 4 axes with safe fallbacks.
        $rows = DB::table('user_answers')
            ->join('questions', 'questions.id', '=', 'user_answers.question_id')
            ->where('user_answers.user_id', $user->id)
            ->where('user_answers.created_at', '>=', Carbon::now()->subDays(30))
            ->select('questions.skill', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN user_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct'))
            ->groupBy('questions.skill')
            ->get();

        $bySkill = $rows->keyBy('skill');
        $skills = ['listening', 'reading', 'writing', 'speaking'];

        return collect($skills)->map(function ($skill) use ($bySkill) {
            $r = $bySkill->get($skill);
            $total = (int) ($r->total ?? 0);
            $correct = (int) ($r->correct ?? 0);
            return [
                'skill' => $skill,
                'total' => $total,
                'correct' => $correct,
                'accuracy' => $total > 0 ? round($correct / $total, 2) : null,
            ];
        })->all();
    }

    private function estimatedBand(User $user): ?float
    {
        $target = (float) ($user->target_band ?? 0);
        if (! $target) return null;

        // Very rough heuristic — refine with calibrated data later.
        // Map accuracy in [0,1] to band [3, target].
        $skills = collect($this->skillBreakdown($user))->pluck('accuracy')->filter();
        if ($skills->isEmpty()) return null;
        $avg = $skills->avg();
        return round(3 + ($target - 3) * $avg, 1);
    }

    private function activityHeatmap(User $user): array
    {
        // Day -> count for the last 30 days.
        $rows = DB::table('user_answers')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subDays(29)->startOfDay())
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        $days = [];
        $cursor = Carbon::now()->subDays(29)->startOfDay();
        for ($i = 0; $i < 30; $i++) {
            $key = $cursor->toDateString();
            $days[] = ['date' => $key, 'count' => (int) ($rows[$key] ?? 0)];
            $cursor->addDay();
        }
        return $days;
    }

    private function weaknessBreakdown(User $user): array
    {
        // Top 5 topics with the lowest accuracy.
        return DB::table('user_answers')
            ->join('questions', 'questions.id', '=', 'user_answers.question_id')
            ->where('user_answers.user_id', $user->id)
            ->whereNotNull('questions.topic_id')
            ->select('questions.topic_id', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN user_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct'))
            ->groupBy('questions.topic_id')
            ->get()
            ->map(function ($r) {
                $total = (int) $r->total;
                $correct = (int) $r->correct;
                return [
                    'topic_id' => (int) $r->topic_id,
                    'total' => $total,
                    'accuracy' => $total > 0 ? round($correct / $total, 2) : null,
                ];
            })
            ->sortBy('accuracy')
            ->take(5)
            ->values()
            ->all();
    }

    private function highLevelStats(User $user): array
    {
        return [
            'xp' => (int) ($user->xp ?? 0),
            'streak' => (int) ($user->streak ?? 0),
            'lessons_completed' => DB::table('user_answers')->where('user_id', $user->id)->distinct()->count('question_id'),
        ];
    }
}