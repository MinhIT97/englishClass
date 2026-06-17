<?php

use Illuminate\Support\Facades\Route;

// TelegramBot module API routes (stateless).
// The webhook itself lives in routes/web.php so that CSRF exemption and
// the secret-token middleware share a single URL.
Route::middleware(['auth'])
    ->prefix('telegrambot')
    ->group(function () {
        // Reserved for future JSON endpoints (e.g. admin stats).
    });
