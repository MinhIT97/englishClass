<?php

namespace Modules\IeltsSet\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class IeltsSetAttempt extends Model
{
    protected $fillable = [
        'ielts_set_id',
        'user_id',
        'status',
        'started_at',
        'submitted_at',
        'score_percent',
        'meta',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'meta' => 'array',
        'score_percent' => 'decimal:2',
    ];

    public function set()
    {
        return $this->belongsTo(IeltsSet::class, 'ielts_set_id');
    }

    public function answers()
    {
        return $this->hasMany(IeltsSetAttemptAnswer::class, 'ielts_set_attempt_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
