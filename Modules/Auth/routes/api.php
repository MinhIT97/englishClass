<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\AdminUserController;

/*
 * Auth Routes
 */

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

/*
 * Admin User Routes
 */
Route::group(['prefix' => 'admin', 'middleware' => 'auth:api'], function () {
    Route::get('users', [AdminUserController::class, 'index']);
    Route::post('users/{id}/approve', [AdminUserController::class, 'approve']);
});
