<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DeployNotifyController;

Route::post('/deploy/notify', [DeployNotifyController::class, 'notify']);
Route::post('/telegram/webhook', [\App\Http\Controllers\Api\TelegramWebhookController::class, 'handle']);
