<?php

namespace App\Services;

use Modules\Practice\Models\UserAnswer;
use Modules\Writing\Models\WritingAttempt;

class PerformanceAnalyticsService
{
    public function studentPerformance(int $userId): array
    {
        $summary = UserAnswer::query()
            ->forUser($userId)
            ->selectRaw('COUNT(*) as total_answers')
            ->selectRaw('SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers')
            ->first();

        $totalAnswers = (int) ($summary->total_answers ?? 0);
        $correctAnswers = (int) ($summary->correct_answers ?? 0);
        $incorrectAnswers = max(0, $totalAnswers - $correctAnswers);

        $skillBreakdown = UserAnswer::query()
            ->forUser($userId)
            ->join('questions', 'questions.id', '=', 'user_answers.question_id')
            ->selectRaw('questions.skill as skill')
            ->selectRaw('COUNT(*) as total_answers')
            ->selectRaw('SUM(CASE WHEN user_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers')
            ->groupBy('questions.skill')
            ->get()
            ->all();

        $skillStats = [];
        $skillAttempts = [];
        $skillCorrectCounts = [];

        foreach ($skillBreakdown as $row) {
            $skill = $row->skill;
            $total = (int) $row->total_answers;
            $correct = (int) $row->correct_answers;

            $skillStats[$skill] = $total > 0 ? round(($correct / $total) * 100) : 0;
            $skillAttempts[$skill] = $total;
            $skillCorrectCounts[$skill] = $correct;
        }

        foreach (['reading', 'listening', 'writing', 'speaking'] as $skill) {
            $skillStats[$skill] = $skillStats[$skill] ?? 0;
            $skillAttempts[$skill] = $skillAttempts[$skill] ?? 0;
            $skillCorrectCounts[$skill] = $skillCorrectCounts[$skill] ?? 0;
        }

        $writingAverageBand = (float) (WritingAttempt::query()
            ->forUser($userId)
            ->avg('band_score') ?? 0);

        if ($writingAverageBand > 0) {
            $skillStats['writing'] = (int) round(($writingAverageBand / 9) * 100);
        }

        $writingAttempts = WritingAttempt::query()
            ->forUser($userId)
            ->count();

        if ($writingAttempts > 0) {
            $skillAttempts['writing'] = $writingAttempts;
        }

        return [
            'accuracy' => $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100) : 0,
            'total_answers' => $totalAnswers,
            'correct_answers' => $correctAnswers,
            'incorrect_answers' => $incorrectAnswers,
            'skill_stats' => $skillStats,
            'skill_attempts' => $skillAttempts,
            'skill_correct_counts' => $skillCorrectCounts,
        ];
    }
}
