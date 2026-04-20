<?php

namespace Modules\Speaking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transcript extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'content',
        'feedback',
    ];

    protected $casts = [
        'feedback' => 'json',
    ];

    public function session()
    {
        return $this->belongsTo(SpeakingSession::class, 'session_id');
    }
}
