<?php

use Illuminate\Support\Facades\Route;
use Modules\Question\Http\Controllers\QuestionController;
use Modules\Question\Http\Controllers\AIQuestionController;

Route::middleware(['auth', 'can:admin-access'])->prefix('admin/questions')->group(function () {
    Route::get('/', [QuestionController::class, 'index'])->name('admin.questions.index');
    Route::get('/create', [QuestionController::class, 'create'])->name('admin.questions.create');
    Route::post('/store', [QuestionController::class, 'store'])->name('admin.questions.store');
    Route::delete('/{id}', [QuestionController::class, 'delete'])->name('admin.questions.delete');
    
    // AI Question Generation
    Route::get('/ai-generate', [AIQuestionController::class, 'index'])->name('admin.questions.ai');
    Route::post('/ai-generate', [AIQuestionController::class, 'generate'])->name('admin.questions.generate');
    Route::post('/store-batch', [AIQuestionController::class, 'store'])->name('admin.questions.store_batch');
    Route::post('/generate-voice', [QuestionController::class, 'generateVoice'])->name('admin.questions.generate_voice');
});
