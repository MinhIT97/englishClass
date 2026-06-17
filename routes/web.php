<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocaleController;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->role === 'admin'
            ? redirect()->route('admin.dashboard')
            : redirect()->route('student.dashboard');
    }
    return view('welcome');
});

Route::get('lang/{locale}', [LocaleController::class, 'setLocale'])->name('set.locale');

// Telegram Webhook — bỏ qua CSRF vì request đến từ Telegram server
// The module's TelegramBot webhook receives the same URL. The bot-aware
// controller handles commands; legacy admin approval flow remains.
Route::post('telegram/webhook', [\App\Http\Controllers\TelegramWebhookController::class, 'handle'])
    ->middleware('telegram.secret')
    ->name('telegram.webhook');

Route::middleware(['auth'])->group(function () {
    Route::post('/ai/chat', [\App\Http\Controllers\Api\AIChatController::class, 'chat'])->name('ai.chat');
});
