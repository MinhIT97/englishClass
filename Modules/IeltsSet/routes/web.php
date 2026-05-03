<?php

use Illuminate\Support\Facades\Route;
use Modules\IeltsSet\Http\Controllers\IeltsSetController;

Route::middleware(['auth', 'can:active-user'])->prefix('student/sets')->group(function () {
    Route::get('/', [IeltsSetController::class, 'index'])->name('student.sets.index');
    Route::get('/{set}', [IeltsSetController::class, 'show'])->name('student.sets.show');
    Route::post('/{set}/start', [IeltsSetController::class, 'start'])->name('student.sets.start');
    Route::get('/{set}/sections/{section}', [IeltsSetController::class, 'section'])->name('student.sets.section');
    Route::post('/{set}/sections/{section}', [IeltsSetController::class, 'submitSection'])->name('student.sets.section.submit');
    Route::post('/{set}/sections/{section}/time', [IeltsSetController::class, 'updateSectionTime'])->name('student.sets.section.time');
});
