<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocaleController;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->role === 'admin' 
            ? redirect()->route('admin.dashboard') 
            : redirect()->route('student.dashboard');
    }
    return view('welcome');
});

Route::get('lang/{locale}', [LocaleController::class, 'setLocale'])->name('set.locale');
