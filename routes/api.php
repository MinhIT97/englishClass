<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DeployNotifyController;

Route::post('/deploy/notify', [DeployNotifyController::class, 'notify']);
Route::post('/telegram/webhook', [\App\Http\Controllers\Api\TelegramWebhookController::class, 'handle']);
Route::post('/ai/chat', [\App\Http\Controllers\Api\AIChatController::class, 'chat']);
