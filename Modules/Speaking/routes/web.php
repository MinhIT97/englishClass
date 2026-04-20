<?php

use Illuminate\Support\Facades\Route;
use Modules\Speaking\Http\Controllers\SpeakingController;

Route::middleware(['auth', 'can:active-user'])->prefix('student/speaking')->group(function () {
    Route::get('/', [SpeakingController::class, 'index'])->name('student.speaking.index');
    Route::post('/start', [SpeakingController::class, 'start'])->name('student.speaking.start');
    Route::post('/chat', [SpeakingController::class, 'chat'])->name('student.speaking.chat');
});
