<?php

use Illuminate\Support\Facades\Route;
use Modules\Speaking\Http\Controllers\SpeakingController;

/*
 * Speaking Routes
 */

Route::group(['prefix' => 'speaking', 'middleware' => 'auth:api'], function () {
    Route::post('start', [SpeakingController::class, 'start']);
    Route::post('transcript', [SpeakingController::class, 'storeTranscript']);
    Route::get('{session}/result', [SpeakingController::class, 'showResult']);
});
