<?php

namespace App\Services;

use App\Models\User;
use Modules\Practice\Models\UserAnswer;

class DashboardService
{
    public function adminStats(): array
    {
        $stats = User::query()
            ->students()
            ->selectRaw('COUNT(*) as total_students')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_approvals")
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_students")
            ->first();

        return [
            'total_students' => (int) ($stats->total_students ?? 0),
            'pending_approvals' => (int) ($stats->pending_approvals ?? 0),
            'active_students' => (int) ($stats->active_students ?? 0),
        ];
    }

    public function studentPerformance(int $userId): array
    {
        $summary = UserAnswer::query()
            ->forUser($userId)
            ->selectRaw('COUNT(*) as total_answers')
            ->selectRaw('SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers')
            ->first();

        $totalAnswers = (int) ($summary->total_answers ?? 0);
        $correctAnswers = (int) ($summary->correct_answers ?? 0);

        $skillStats = UserAnswer::query()
            ->forUser($userId)
            ->join('questions', 'questions.id', '=', 'user_answers.question_id')
            ->selectRaw('questions.skill as skill')
            ->selectRaw('COUNT(*) as total_answers')
            ->selectRaw('SUM(CASE WHEN user_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers')
            ->groupBy('questions.skill')
            ->get()
            ->mapWithKeys(function ($row) {
                $total = (int) $row->total_answers;
                $correct = (int) $row->correct_answers;

                return [
                    $row->skill => $total > 0 ? round(($correct / $total) * 100) : 0,
                ];
            })
            ->all();

        foreach (['reading', 'listening', 'writing', 'speaking'] as $skill) {
            $skillStats[$skill] = $skillStats[$skill] ?? 0;
        }

        return [
            'accuracy' => $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100) : 0,
            'skill_stats' => $skillStats,
        ];
    }
}
