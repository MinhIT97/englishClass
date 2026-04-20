<?php

namespace Modules\Gamification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    /**
     * Show global leaderboard.
     */
    public function index()
    {
        $topStudents = User::where('role', 'student')
            ->orderBy('xp', 'desc')
            ->limit(10)
            ->get();

        $activeStreaks = User::where('role', 'student')
            ->where('streak', '>', 0)
            ->orderBy('streak', 'desc')
            ->limit(5)
            ->get();

        return view('gamification::leaderboard', compact('topStudents', 'activeStreaks'));
    }
}
