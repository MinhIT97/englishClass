<?php

namespace Modules\TelegramBot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Join row between a ReadingPassage and a Question.
 *
 * Decoupling the join from a direct hasMany on Question keeps the question
 * bank (Modules/Question) reusable across skills while still letting a
 * passage own a specific ordered list of questions.
 */
class ReadingPassageQuestion extends Model
{
    protected $table = 'reading_passage_questions';

    protected $fillable = [
        'reading_passage_id',
        'question_id',
        'order_index',
    ];

    protected $casts = [
        'order_index' => 'integer',
    ];

    public function passage(): BelongsTo
    {
        return $this->belongsTo(ReadingPassage::class, 'reading_passage_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(\Modules\Question\Models\Question::class, 'question_id');
    }
}
