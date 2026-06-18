<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyPlan extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'scheduled_at',
        'duration_minutes',
        'type',
        'status',
        'reminder_sent_at',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public const TYPES = ['lesson', 'mock_test', 'review', 'practice', 'rest'];
    public const STATUSES = ['pending', 'in_progress', 'completed', 'skipped'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}