<?php

namespace App\Services;

use App\Models\User;

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
}
