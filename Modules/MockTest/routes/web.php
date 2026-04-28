<?php

use Illuminate\Support\Facades\Route;
use Modules\MockTest\Http\Controllers\MockTestController;

Route::middleware(['auth', 'can:active-user'])->prefix('student/test')->group(function () {
    Route::get('/', [MockTestController::class, 'index'])->name('student.test.index');
    Route::get('/start', [MockTestController::class, 'startFullTest'])->name('student.test.start');
});
