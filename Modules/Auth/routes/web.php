<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('login', [AuthController::class, 'webLogin']);
    Route::post('register', [AuthController::class, 'webRegister']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    
    // Profile Settings
    Route::get('settings', [\Modules\Auth\Http\Controllers\ProfileController::class, 'index'])->name('settings');
    Route::post('settings', [\Modules\Auth\Http\Controllers\ProfileController::class, 'update'])->name('settings.update');
    
    // Redirect logic
    Route::get('/', function () {
        return auth()->user()->role === 'admin' 
            ? redirect('/admin/dashboard') 
            : redirect('/student/dashboard');
    });

    Route::group(['prefix' => 'admin', 'middleware' => 'can:admin-access'], function () {
        Route::get('dashboard', [AuthController::class, 'adminDashboard'])->name('admin.dashboard');
        Route::get('users', [\Modules\Auth\Http\Controllers\AdminUserController::class, 'webIndex'])->name('admin.users');
        Route::post('users/{id}/approve', [\Modules\Auth\Http\Controllers\AdminUserController::class, 'webApprove'])->name('admin.users.approve');
    });

    Route::get('student/dashboard', [AuthController::class, 'studentDashboard'])->name('student.dashboard');
});
