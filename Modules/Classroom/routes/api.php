<?php

use Illuminate\Support\Facades\Route;
use Modules\Classroom\Http\Controllers\ClassroomController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('classrooms', ClassroomController::class)->names('classroom');
});
