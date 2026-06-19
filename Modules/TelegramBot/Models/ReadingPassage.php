<?php

namespace Modules\TelegramBot\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reading comprehension passage.
 *
 * A passage groups N questions (mcq, gap-fill, matching) into a single
 * reviewable unit. The user finishes all questions in one sitting and the
 * whole passage is graded at once, then entered into a per-user SM-2
 * schedule (see ReadingPassageReview).
 */
class ReadingPassage extends Model
{
    protected $table = 'reading_passages';

    protected $fillable = [
        'slug',
        'title',
        'body',
        'source',
        'difficulty',
        'word_count',
        'estimated_minutes',
        'tags',
        'topic_id',
        'is_active',
    ];

    protected $casts = [
        'word_count' => 'integer',
        'estimated_minutes' => 'integer',
        'tags' => 'array',
        'is_active' => 'boolean',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function passageQuestions(): HasMany
    {
        return $this->hasMany(ReadingPassageQuestion::class, 'reading_passage_id')
            ->orderBy('order_index');
    }

    public function questions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Question\Models\Question::class,
            'reading_passage_questions',
            'reading_passage_id',
            'question_id'
        )->withPivot('order_index');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ReadingPassageReview::class, 'reading_passage_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ReadingAttempt::class, 'reading_passage_id');
    }

    /**
     * Reviews scoped to a specific user (used to check maturity and queue).
     */
    public function reviewForUser(int $userId): ?ReadingPassageReview
    {
        return $this->reviews()->where('user_id', $userId)->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTopic(Builder $query, ?int $topicId): Builder
    {
        if ($topicId === null) {
            return $query;
        }
        return $query->where('topic_id', $topicId);
    }

    public function scopeDifficulty(Builder $query, ?string $difficulty): Builder
    {
        if (! $difficulty) {
            return $query;
        }
        return $query->where('difficulty', $difficulty);
    }
}
