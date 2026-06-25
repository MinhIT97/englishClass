<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\AdminUserController;

/*
 * Auth Routes
 *
 * SECURITY: Same rate limits as the web form — IP-based throttle to
 * stop credential stuffing and mass account creation via the API.
 */

Route::post('register', [AuthController::class, 'register'])->middleware('throttle:3,60');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

/*
 * SECURITY (SEC-006): JWT logout. Invalidates the current token on the
 * server side so a stolen token cannot be reused after logout. The
 * blacklist is enabled in config/jwt.php so JWTAuth::invalidate() works.
 */
Route::post('logout', function () {
    auth('api')->logout();
    return response()->json(['message' => 'Logged out successfully.']);
})->middleware('auth:api');

/*
 * Admin User Routes
 */
Route::group(['prefix' => 'admin', 'middleware' => 'auth:api'], function () {
    Route::get('users', [AdminUserController::class, 'index']);
    Route::post('users/{id}/approve', [AdminUserController::class, 'approve']);
});
