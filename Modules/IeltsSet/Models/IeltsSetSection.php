<?php

namespace Modules\IeltsSet\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Question\Models\Question;

class IeltsSetSection extends Model
{
    protected $fillable = [
        'ielts_set_id',
        'skill',
        'title',
        'instructions',
        'section_order',
        'time_limit_minutes',
    ];

    public function set()
    {
        return $this->belongsTo(IeltsSet::class, 'ielts_set_id');
    }

    public function questions()
    {
        return $this->belongsToMany(
            Question::class,
            'ielts_set_section_question',
            'ielts_set_section_id',
            'question_id'
        )->withPivot('question_order')->orderBy('ielts_set_section_question.question_order');
    }
}
