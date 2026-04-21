<?php

use Illuminate\Support\Facades\Route;
use Modules\Course\Http\Controllers\CourseController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('courses/{course}/enroll', [CourseController::class, 'enroll'])->name('course.enroll');
    Route::resource('courses', CourseController::class)->names('course');
});
