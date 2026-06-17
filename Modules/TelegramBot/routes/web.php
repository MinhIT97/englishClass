<?php

use Illuminate\Support\Facades\Route;
use Modules\TelegramBot\Http\Controllers\LinkingCodeController;
use Modules\TelegramBot\Http\Controllers\TelegramSettingsController;

// Web routes for Telegram linking/settings. Mounted under `web` middleware.
Route::middleware(['auth'])->prefix('student/settings/telegram')->group(function () {
    Route::get('/', [TelegramSettingsController::class, 'show'])->name('student.telegram.settings');
    Route::post('/linking-code', [LinkingCodeController::class, 'generate'])->name('student.telegram.linking-code');
    Route::post('/unlink', [TelegramSettingsController::class, 'unlink'])->name('student.telegram.unlink');
    Route::post('/dismiss-banner', [TelegramSettingsController::class, 'dismissBanner'])
        ->name('student.telegram.dismiss-banner');
});
