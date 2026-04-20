<?php

use Illuminate\Support\Facades\Route;
use Modules\Question\Http\Controllers\QuestionController;

/*
 * Question Routes
 */

Route::get('questions', [QuestionController::class, 'index']);
