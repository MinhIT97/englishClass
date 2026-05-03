<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackLog extends Model
{
    protected $fillable = [
        'feedback_id',
        'user_id',
        'action',
        'content',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function feedback()
    {
        return $this->belongsTo(Feedback::class);
    }
}

