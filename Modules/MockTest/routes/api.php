<?php

use Illuminate\Support\Facades\Route;
use Modules\MockTest\Http\Controllers\MockTestController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('mocktests', MockTestController::class)->names('mocktest');
});
