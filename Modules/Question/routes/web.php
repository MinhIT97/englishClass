<?php

use Illuminate\Support\Facades\Route;
use Modules\Question\Http\Controllers\QuestionController;
use Modules\Question\Http\Controllers\AIQuestionController;

Route::middleware(['auth', 'can:admin-access'])->prefix('admin/question')->group(function () {
    Route::get('/', [QuestionController::class, 'index'])->name('question.index');
    Route::get('/create', [QuestionController::class, 'create'])->name('question.create');
    Route::post('/store', [QuestionController::class, 'store'])->name('question.store');
    Route::delete('/{id}', [QuestionController::class, 'delete'])->name('question.delete');
    
    // AI Question Generation
    Route::get('/ai-generate', [AIQuestionController::class, 'index'])->name('admin.questions.ai');
    Route::post('/ai-generate', [AIQuestionController::class, 'generate'])->name('api.questions.generate');
    Route::post('/store-batch', [AIQuestionController::class, 'store'])->name('admin.questions.store_batch');
});
