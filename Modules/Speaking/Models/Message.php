<?php

namespace Modules\Speaking\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'ai_feedback', 'audio_url'];
    
    protected $casts = [
        'ai_feedback' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
