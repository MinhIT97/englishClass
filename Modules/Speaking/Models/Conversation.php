<?php

namespace Modules\Speaking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'session_id'];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
