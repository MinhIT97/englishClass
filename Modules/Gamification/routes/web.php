<?php

use Illuminate\Support\Facades\Route;
use Modules\Gamification\Http\Controllers\LeaderboardController;

Route::middleware(['auth', 'can:active-user'])->group(function () {
    Route::get('student/leaderboard', [LeaderboardController::class, 'index'])->name('student.leaderboard');
});
