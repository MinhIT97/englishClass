<?php

use Illuminate\Support\Facades\Route;
use Modules\TelegramBot\Http\Controllers\LinkingCodeController;
use Modules\TelegramBot\Http\Controllers\ReadingPassageAdminController;
use Modules\TelegramBot\Http\Controllers\ReadingPassageReviewController;
use Modules\TelegramBot\Http\Controllers\TelegramSettingsController;

// Web routes for Telegram linking/settings. Mounted under `web` middleware.
Route::middleware(['auth'])->prefix('student/settings/telegram')->group(function () {
    Route::get('/', [TelegramSettingsController::class, 'show'])->name('student.telegram.settings');
    Route::post('/linking-code', [LinkingCodeController::class, 'generate'])->name('student.telegram.linking-code');
    Route::post('/unlink', [TelegramSettingsController::class, 'unlink'])->name('student.telegram.unlink');
    Route::post('/dismiss-banner', [TelegramSettingsController::class, 'dismissBanner'])
        ->name('student.telegram.dismiss-banner');
});

// Reading-comprehension review (web). Mirrors the Flashcard SRS flow:
// browse the library, run a review session, grade a passage, fetch stats.
Route::middleware(['auth', 'can:active-user'])->prefix('reading-review')->name('reading-review.')->group(function () {
    Route::get('/', [ReadingPassageReviewController::class, 'index'])->name('index');
    Route::get('/session', [ReadingPassageReviewController::class, 'session'])->name('session');
    Route::post('/passages/{passage}/grade', [ReadingPassageReviewController::class, 'grade'])->name('grade');
    Route::post('/passages/{passage}/enrol', [ReadingPassageReviewController::class, 'enrol'])->name('enrol');
    Route::get('/stats', [ReadingPassageReviewController::class, 'stats'])->name('stats');
});

// Admin CRUD for reading passages. Mounted under `admin/reading-passages`
// so the URL namespace stays clean. `can:admin-access` is the same gate
// the rest of the admin area uses.
Route::middleware(['auth', 'can:admin-access', 'audit.admin'])
    ->prefix('admin/reading-passages')
    ->name('admin.reading-passages.')
    ->group(function () {
        Route::get('/', [ReadingPassageAdminController::class, 'index'])->name('index');
        Route::get('/create', [ReadingPassageAdminController::class, 'create'])->name('create');
        Route::post('/', [ReadingPassageAdminController::class, 'store'])->name('store');
        Route::get('/{passage}/edit', [ReadingPassageAdminController::class, 'edit'])->name('edit');
        Route::put('/{passage}', [ReadingPassageAdminController::class, 'update'])->name('update');
        Route::delete('/{passage}', [ReadingPassageAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{passage}/toggle', [ReadingPassageAdminController::class, 'toggle'])->name('toggle');
    });
