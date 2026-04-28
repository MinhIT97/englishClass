<?php

use Illuminate\Support\Facades\Route;
use Modules\Practice\Http\Controllers\PracticeController;

Route::middleware(['auth', 'can:active-user'])->prefix('student/practice')->group(function () {
    Route::get('/', [PracticeController::class, 'index'])->name('student.practice.index');
    Route::get('/drill/{skill}', [PracticeController::class, 'showDrill'])->name('student.practice.drill');
    Route::post('/submit', [PracticeController::class, 'submitAnswer'])->name('student.practice.submit');
    Route::post('/submit-speaking', [PracticeController::class, 'submitSpeaking'])->name('student.practice.submit.speaking');
});
