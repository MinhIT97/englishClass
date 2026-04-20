<?php

namespace Modules\Speaking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class SpeakingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'started_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transcripts()
    {
        return $this->hasMany(Transcript::class, 'session_id');
    }
}
