<?php

use Illuminate\Support\Facades\Route;
use Modules\Practice\Http\Controllers\PracticeController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('practices', PracticeController::class)->names('practice');
});
