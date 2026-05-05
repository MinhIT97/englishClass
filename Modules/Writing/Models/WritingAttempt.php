<?php

namespace Modules\Writing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class WritingAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'task_type',
        'essay_content',
        'band_score',
        'feedback',
        'revised_essay',
    ];

    protected $casts = [
        'feedback' => 'json',
    ];

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
