<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Classroom\Models\Classroom;

class Assignment extends Model
{
    protected $fillable = ['classroom_id', 'title', 'description', 'due_at', 'rubric'];

    protected $casts = [
        'due_at' => 'datetime',
        'rubric' => 'array',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
