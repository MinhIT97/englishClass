<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'notify_lesson_reminder', 'notify_quota_request',
        'notify_achievement', 'notify_feedback',
        'notification_digest',
        'daily_review_goal', 'preferred_study_time', 'session_duration_minutes',
        'show_in_leaderboard', 'show_study_notes_publicly',
        'locale',
    ];

    protected $casts = [
        'notify_lesson_reminder' => 'boolean',
        'notify_quota_request' => 'boolean',
        'notify_achievement' => 'boolean',
        'notify_feedback' => 'boolean',
        'show_in_leaderboard' => 'boolean',
        'show_study_notes_publicly' => 'boolean',
        'daily_review_goal' => 'integer',
        'session_duration_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}