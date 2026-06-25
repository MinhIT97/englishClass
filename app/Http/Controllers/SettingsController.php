<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function show(Request $request): View
    {
        $pref = UserPreference::firstOrCreate(['user_id' => $request->user()->id]);

        return view('settings.preferences', compact('pref'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notify_lesson_reminder' => ['boolean'],
            'notify_quota_request' => ['boolean'],
            'notify_achievement' => ['boolean'],
            'notify_feedback' => ['boolean'],
            'notification_digest' => ['in:realtime,daily,weekly,off'],
            'daily_review_goal' => ['integer', 'min:5', 'max:200'],
            'preferred_study_time' => ['nullable', 'in:morning,afternoon,evening,night'],
            'session_duration_minutes' => ['integer', 'min:10', 'max:120'],
            'show_in_leaderboard' => ['boolean'],
            'show_study_notes_publicly' => ['boolean'],
            'locale' => ['in:vi,en'],
        ]);

        UserPreference::updateOrCreate(['user_id' => $request->user()->id], $data);

        return back()->with('success', 'Đã lưu cài đặt.');
    }

    /**
     * Export all user data — GDPR / Vietnamese PDPA right of access.
     */
    public function export(Request $request)
    {
        $user = $request->user();
        $data = [
            'profile' => $user->only(['name', 'email', 'role', 'status', 'target_band', 'xp', 'streak', 'created_at']),
            'preferences' => $user->preferences?->toArray(),
            'exported_at' => now()->toIso8601String(),
        ];

        // SEC-031: audit GDPR data export — required by compliance (records of disclosure).
        app(AuditLogger::class)->log('settings.gdpr_export', null, [
            'user_id' => $user->id,
            'format' => 'json',
        ]);

        return response()->json($data, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="user-data-' . $user->id . '.json"',
        ]);
    }
}