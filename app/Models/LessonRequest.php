<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user-submitted request to raise their daily lesson quota, reviewed
 * by an admin. See database/migrations/.../create_lesson_requests_table.php
 * for column rationale.
 */
class LessonRequest extends Model
{
    public const TYPE_COURSE = 'course';
    public const TYPE_CLASSROOM = 'classroom';
    public const TYPE_DAILY_LESSON = 'daily_lesson';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'lesson_type',
        'requested_extra',
        'reason',
        'status',
        'approved_extra',
        'grant_unlimited',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'grant_unlimited' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}