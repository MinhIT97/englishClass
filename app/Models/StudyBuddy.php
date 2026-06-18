<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StudyBuddy extends Model
{
    /** Self-referential pivot: users who marked each other as buddies. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'study_buddy_user')
            ->withPivot('matched_at')
            ->withTimestamps();
    }
}