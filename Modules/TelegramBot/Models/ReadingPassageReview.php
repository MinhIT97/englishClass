<?php

namespace Modules\TelegramBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SM-2 spaced repetition schedule for a ReadingPassage (per user).
 *
 * Mirrors tgb_review_schedules for vocabulary entries, kept in a separate
 * table for two reasons documented in the migration:
 *   1. vocabulary_entry_id has a hard FK we don't want to touch,
 *   2. passages and words are graded and mature independently.
 *
 * The same SpacedRepetitionService::grade() method works for both schedule
 * types because it operates on the columns (ease_factor, interval_days,
 * repetitions, next_review_at, last_grade) and not on a specific FK.
 */
class ReadingPassageReview extends Model
{
    protected $table = 'tgb_reading_passage_reviews';

    public const GRADE_AGAIN = 0;
    public const GRADE_HARD = 1;
    public const GRADE_GOOD = 2;
    public const GRADE_EASY = 3;

    /**
     * Minimum repetitions for a passage to be considered "mature" (i.e.
     * counts toward topic completion). Matches ReviewSchedule::MATURE_REPETITIONS
     * so both schedules are checked against the same bar.
     */
    public const MATURE_REPETITIONS = 2;

    protected $fillable = [
        'user_id',
        'reading_passage_id',
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

    public function passage(): BelongsTo
    {
        return $this->belongsTo(ReadingPassage::class, 'reading_passage_id');
    }

    public function isMature(): bool
    {
        return $this->repetitions >= self::MATURE_REPETITIONS;
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('next_review_at')
              ->orWhere('next_review_at', '<=', now());
        });
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
