<?php

use Illuminate\Support\Facades\Route;
use Modules\Gamification\Http\Controllers\GamificationController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('gamifications', GamificationController::class)->names('gamification');
});
