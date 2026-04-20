<?php

use Illuminate\Support\Facades\Route;
use Modules\Writing\Http\Controllers\WritingController;

Route::middleware(['auth', 'can:active-user'])->prefix('student/writing')->group(function () {
    Route::get('/', [WritingController::class, 'index'])->name('student.writing.index');
    Route::post('submit', [WritingController::class, 'submit'])->name('student.writing.submit');
    Route::get('{id}/result', [WritingController::class, 'show'])->name('student.writing.show');
});
