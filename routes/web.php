<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LessonRequestController;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->role === 'admin'
            ? redirect()->route('admin.dashboard')
            : redirect()->route('student.dashboard');
    }
    return view('welcome');
});

Route::get('lang/{locale}', [LocaleController::class, 'setLocale'])->name('set.locale');

// Telegram Webhook — bỏ qua CSRF vì request đến từ Telegram server
// The module's TelegramBot webhook receives the same URL. The bot-aware
// controller handles commands; legacy admin approval flow remains.
Route::post('telegram/webhook', [\App\Http\Controllers\TelegramWebhookController::class, 'handle'])
    ->middleware('telegram.secret')
    ->name('telegram.webhook');

Route::middleware(['auth'])->group(function () {
    Route::post('/ai/chat', [\App\Http\Controllers\Api\AIChatController::class, 'chat'])
        ->middleware('throttle:ai')
        ->name('ai.chat');

    // AI Tutor endpoints
    Route::post('/ai/tutor', [\App\Http\Controllers\AiTutorController::class, 'ask'])
        ->middleware('throttle:ai')
        ->name('ai.tutor.ask');
    Route::post('/ai/tutor/explain', [\App\Http\Controllers\AiTutorController::class, 'explain'])
        ->middleware('throttle:ai')
        ->name('ai.tutor.explain');
    Route::post('/ai/tutor/suggest', [\App\Http\Controllers\AiTutorController::class, 'suggest'])
        ->middleware('throttle:ai')
        ->name('ai.tutor.suggest');
    Route::post('/ai/tutor/clear', [\App\Http\Controllers\AiTutorController::class, 'clear'])
        ->name('ai.tutor.clear');

    // User-submitted lesson quota requests
    Route::post('lesson-requests', [LessonRequestController::class, 'store'])
        ->middleware('throttle:lesson-requests')
        ->name('lesson-requests.store');

    // Flashcard SRS
    Route::get('flashcards', [\App\Http\Controllers\FlashcardController::class, 'index'])->name('flashcards.index');
    Route::post('flashcards/{reviewSchedule}/grade', [\App\Http\Controllers\FlashcardController::class, 'grade'])->name('flashcards.grade');
    Route::get('flashcards/stats', [\App\Http\Controllers\FlashcardController::class, 'stats'])->name('flashcards.stats');

    // Study planner
    Route::get('study-plan', [\App\Http\Controllers\StudyPlanController::class, 'index'])->name('study-plan.index');
    Route::post('study-plan', [\App\Http\Controllers\StudyPlanController::class, 'store'])->name('study-plan.store');
    Route::post('study-plan/{plan}/complete', [\App\Http\Controllers\StudyPlanController::class, 'complete'])->name('study-plan.complete');
    Route::delete('study-plan/{plan}', [\App\Http\Controllers\StudyPlanController::class, 'destroy'])->name('study-plan.destroy');

    // Daily quests
    Route::get('quests', [\App\Http\Controllers\QuestController::class, 'index'])->name('quests.index');

    // Community — public study notes + comments + buddy match
    Route::get('community/notes', [\App\Http\Controllers\CommunityController::class, 'notesIndex'])->name('community.notes.index');
    // SEC-033: throttle community write endpoints to prevent spam/abuse.
    Route::post('community/notes', [\App\Http\Controllers\CommunityController::class, 'noteStore'])
        ->middleware('throttle:10,60')
        ->name('community.notes.store');
    Route::post('community/comments', [\App\Http\Controllers\CommunityController::class, 'commentStore'])
        ->middleware('throttle:10,60')
        ->name('community.comments.store');
    Route::get('community/find-buddy', [\App\Http\Controllers\CommunityController::class, 'findBuddy'])->name('community.buddy');

    // Student analytics
    Route::get('analytics', [\App\Http\Controllers\AnalyticsController::class, 'show'])->name('analytics.show');

    // Teacher dashboard
    Route::middleware('role:teacher,admin')->group(function () {
        Route::get('teacher/dashboard', [\App\Http\Controllers\TeacherController::class, 'dashboard'])->name('teacher.dashboard');
    });

    // Global search
    Route::get('search', \App\Http\Controllers\SearchController::class)->name('search');

    // Settings + GDPR export
    Route::get('settings/preferences', [\App\Http\Controllers\SettingsController::class, 'show'])->name('settings.preferences');
    Route::put('settings/preferences', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.preferences.update');
    // SEC-032: throttle GDPR export — limit to 10/min per user (rate-limit sensitive disclosure).
    Route::get('settings/export', [\App\Http\Controllers\SettingsController::class, 'export'])
        ->middleware('throttle:10,60')
        ->name('settings.preferences.export');
});

Route::middleware(['auth', 'can:admin-access', 'audit.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('lesson-requests', [LessonRequestController::class, 'index'])->name('lesson-requests.index');
    Route::post('lesson-requests/{lessonRequest}/review', [LessonRequestController::class, 'review'])->name('lesson-requests.review');
});
