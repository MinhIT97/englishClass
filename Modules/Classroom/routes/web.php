<?php

use Illuminate\Support\Facades\Route;
use Modules\Classroom\Http\Controllers\ClassroomController;

Route::middleware(['auth'])->group(function () {
    Route::get('classroom', [ClassroomController::class, 'index'])->name('classroom.index');
    Route::post('classroom', [ClassroomController::class, 'store'])->name('classroom.store');
    Route::get('classroom/{classroom}', [ClassroomController::class, 'show'])->name('classroom.show');
    Route::post('classroom/join', [ClassroomController::class, 'join'])->name('classroom.join');
    Route::post('classroom/{classroom}/post', [ClassroomController::class, 'storePost'])->name('classroom.post.store');
    Route::post('classroom/post/{post}/feedback', [ClassroomController::class, 'storeFeedback'])->name('classroom.post.feedback');
    Route::post('classroom/post/{post}/comment', [ClassroomController::class, 'storeComment'])->name('classroom.post.comment');
});
