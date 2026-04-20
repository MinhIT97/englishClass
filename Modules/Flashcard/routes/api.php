<?php

use Illuminate\Support\Facades\Route;
use Modules\Flashcard\Http\Controllers\FlashcardController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('flashcards', FlashcardController::class)->names('flashcard');
});
