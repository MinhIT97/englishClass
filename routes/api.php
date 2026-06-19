<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DeployNotifyController;

Route::post('/deploy/notify', [DeployNotifyController::class, 'notify'])
    ->middleware('auth.deploy');
