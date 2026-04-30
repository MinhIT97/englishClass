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
Route::post('telegram/webhook', [\App\Http\Controllers\TelegramWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('telegram.webhook');
