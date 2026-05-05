<?php

namespace Modules\IeltsSet\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Question\Models\Question;

class IeltsSetAttemptAnswer extends Model
{
    protected $fillable = [
        'ielts_set_attempt_id',
        'ielts_set_section_id',
        'question_id',
        'answer_text',
        'is_correct',
        'points_earned',
        'correct_answer',
        'feedback',
        'answered_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'answered_at' => 'datetime',
    ];

    public function attempt()
    {
        return $this->belongsTo(IeltsSetAttempt::class, 'ielts_set_attempt_id');
    }

    public function section()
    {
        return $this->belongsTo(IeltsSetSection::class, 'ielts_set_section_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
