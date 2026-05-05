<?php

use Illuminate\Support\Facades\Route;
use Modules\IeltsSet\Http\Controllers\AdminIeltsSetController;
use Modules\IeltsSet\Http\Controllers\IeltsSetController;

Route::middleware(['auth', 'can:admin-access'])->prefix('admin/sets')->group(function () {
    Route::get('/', [AdminIeltsSetController::class, 'index'])->name('admin.sets.index');
    Route::get('/create', [AdminIeltsSetController::class, 'create'])->name('admin.sets.create');
    Route::post('/store', [AdminIeltsSetController::class, 'store'])->name('admin.sets.store');
    Route::get('/{set}/edit', [AdminIeltsSetController::class, 'edit'])->name('admin.sets.edit');
    Route::put('/{set}', [AdminIeltsSetController::class, 'update'])->name('admin.sets.update');
    Route::delete('/{set}', [AdminIeltsSetController::class, 'destroy'])->name('admin.sets.destroy');
});

Route::middleware(['auth', 'can:active-user'])->prefix('student/sets')->group(function () {
    Route::get('/', [IeltsSetController::class, 'index'])->name('student.sets.index');
    Route::get('/{set}', [IeltsSetController::class, 'show'])->name('student.sets.show');
    Route::post('/{set}/start', [IeltsSetController::class, 'start'])->name('student.sets.start');
    Route::get('/{set}/sections/{section}', [IeltsSetController::class, 'section'])->name('student.sets.section');
    Route::post('/{set}/sections/{section}', [IeltsSetController::class, 'submitSection'])->name('student.sets.section.submit');
    Route::post('/{set}/sections/{section}/complete-speaking', [IeltsSetController::class, 'completeSpeakingSection'])->name('student.sets.section.complete-speaking');
    Route::post('/{set}/sections/{section}/time', [IeltsSetController::class, 'updateSectionTime'])->name('student.sets.section.time');
});
