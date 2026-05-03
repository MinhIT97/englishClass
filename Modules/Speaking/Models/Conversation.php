<?php

namespace Modules\Speaking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'session_id'];

    public function scopeForSessionOfUser($query, string $sessionId, int $userId)
    {
        return $query
            ->where('session_id', $sessionId)
            ->where('user_id', $userId);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
