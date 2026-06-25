<?php

use Illuminate\Support\Facades\Route;
use Modules\Speaking\Http\Controllers\SpeakingController;

Route::middleware(['auth', 'can:active-user'])->prefix('student/speaking')->group(function () {
    Route::get('/', [SpeakingController::class, 'index'])->name('student.speaking.index');
    // SECURITY (SEC-012): /start and /chat invoke Gemini AI + TTS — cost-bearing endpoints.
    // The `ai-speaking` limiter (20/min/user) is registered in AppServiceProvider.
    Route::post('/start', [SpeakingController::class, 'start'])
        ->middleware('throttle:ai-speaking')
        ->name('student.speaking.start');
    Route::post('/chat', [SpeakingController::class, 'chat'])
        ->middleware('throttle:ai-speaking')
        ->name('student.speaking.chat');
    Route::get('/poll', [SpeakingController::class, 'poll'])->name('student.speaking.poll');
});
