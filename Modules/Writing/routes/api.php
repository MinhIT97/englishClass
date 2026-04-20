<?php

use Illuminate\Support\Facades\Route;
use Modules\Writing\Http\Controllers\WritingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('writings', WritingController::class)->names('writing');
});
