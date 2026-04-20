<?php

use Illuminate\Support\Facades\Route;
use Modules\Course\Http\Controllers\CourseController;

/*
 * API Routes
 */

Route::apiResource('courses', CourseController::class);
