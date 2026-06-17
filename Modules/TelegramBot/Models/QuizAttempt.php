<?php

namespace Modules\TelegramBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    protected $table = 'tgb_quiz_attempts';

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    public const TYPE_FILL_BLANK = 'fill_blank';
    public const TYPE_WORD_SCRAMBLE = 'word_scramble';
    public const TYPE_MATCH_PAIRS = 'match_pairs';

    protected $fillable = [
        'user_id',
        'daily_lesson_id',
        'vocabulary_entry_id',
        'quiz_type',
        'question_payload',
        'user_answer',
        'is_correct',
        'xp_awarded',
        'attempted_at',
    ];

    protected $casts = [
        'question_payload' => 'array',
        'is_correct' => 'boolean',
        'xp_awarded' => 'integer',
        'attempted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyLesson(): BelongsTo
    {
        return $this->belongsTo(DailyLesson::class, 'daily_lesson_id');
    }

    public function vocabularyEntry(): BelongsTo
    {
        return $this->belongsTo(VocabularyEntry::class, 'vocabulary_entry_id');
    }
}
