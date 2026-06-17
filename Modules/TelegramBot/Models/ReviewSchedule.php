<?php

namespace Modules\TelegramBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewSchedule extends Model
{
    protected $table = 'tgb_review_schedules';

    public const GRADE_AGAIN = 0;
    public const GRADE_HARD = 1;
    public const GRADE_GOOD = 2;
    public const GRADE_EASY = 3;

    protected $fillable = [
        'user_id',
        'vocabulary_entry_id',
        'ease_factor',
        'interval_days',
        'repetitions',
        'next_review_at',
        'last_reviewed_at',
        'last_grade',
    ];

    protected $casts = [
        'ease_factor' => 'decimal:2',
        'interval_days' => 'integer',
        'repetitions' => 'integer',
        'next_review_at' => 'datetime',
        'last_reviewed_at' => 'datetime',
        'last_grade' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vocabularyEntry(): BelongsTo
    {
        return $this->belongsTo(VocabularyEntry::class, 'vocabulary_entry_id');
    }

    public function scopeDue($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('next_review_at')
              ->orWhere('next_review_at', '<=', now());
        });
    }
}
