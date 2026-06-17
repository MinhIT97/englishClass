<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// TelegramBot: send daily lessons. Runs every hour; the command itself
// filters profiles whose daily_send_hour matches the current hour
// (Asia/Ho_Chi_Minh timezone).
Schedule::command('tgb:send-daily')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->timezone('Asia/Ho_Chi_Minh');
