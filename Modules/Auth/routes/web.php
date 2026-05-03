<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\NotificationController;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('login', [AuthController::class, 'webLogin']);
    Route::post('register', [AuthController::class, 'webRegister']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    
    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unreadCount');
    
    // Profile Settings
    Route::get('settings', [\Modules\Auth\Http\Controllers\ProfileController::class, 'index'])->name('settings');
    Route::post('settings', [\Modules\Auth\Http\Controllers\ProfileController::class, 'update'])->name('settings.update');
    
    // Feedback
    Route::post('feedback', [\App\Http\Controllers\FeedbackController::class, 'store'])->name('feedback.store');

    
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
        
        // Admin Feedback
        Route::get('feedback', [\App\Http\Controllers\Admin\AdminFeedbackController::class, 'index'])->name('admin.feedback.index');
        Route::patch('feedback/{feedback}/status', [\App\Http\Controllers\Admin\AdminFeedbackController::class, 'updateStatus'])->name('admin.feedback.updateStatus');
        Route::post('feedback/{feedback}/assign', [\App\Http\Controllers\Admin\AdminFeedbackController::class, 'assignUser'])->name('admin.feedback.assign');
        Route::post('feedback/{feedback}/note', [\App\Http\Controllers\Admin\AdminFeedbackController::class, 'addNote'])->name('admin.feedback.addNote');
        Route::delete('feedback/{feedback}', [\App\Http\Controllers\Admin\AdminFeedbackController::class, 'destroy'])->name('admin.feedback.destroy');

    });


    Route::get('student/dashboard', [AuthController::class, 'studentDashboard'])->name('student.dashboard');
});
