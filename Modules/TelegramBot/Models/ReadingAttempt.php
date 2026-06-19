<?php

namespace Modules\TelegramBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt at a single question inside a reading passage.
 *
 * Recorded on every answer submission (web or Telegram) so we can compute
 * XP, accuracy, and per-passage stats. One user submitting a passage usually
 * generates N rows (N = number of questions in that passage).
 */
class ReadingAttempt extends Model
{
    protected $table = 'reading_attempts';

    protected $fillable = [
        'user_id',
        'reading_passage_id',
        'question_id',
        'student_answer',
        'is_correct',
        'points_earned',
        'time_spent_ms',
        'attempted_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'points_earned' => 'integer',
        'time_spent_ms' => 'integer',
        'attempted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function passage(): BelongsTo
    {
        return $this->belongsTo(ReadingPassage::class, 'reading_passage_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(\Modules\Question\Models\Question::class, 'question_id');
    }
}
