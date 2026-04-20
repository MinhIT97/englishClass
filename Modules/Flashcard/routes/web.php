<?php

use Illuminate\Support\Facades\Route;
use Modules\Flashcard\Http\Controllers\FlashcardController;

Route::middleware(['auth', 'can:active-user'])->prefix('student/flashcards')->group(function () {
    Route::get('/', [FlashcardController::class, 'index'])->name('student.flashcards.index');
    Route::post('/save', [FlashcardController::class, 'saveToPersonal'])->name('student.flashcards.save');
});
