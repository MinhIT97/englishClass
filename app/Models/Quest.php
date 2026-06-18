<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily quest definition. A user completes the quest by hitting
 * the target count on the named metric within the active window
 * (start_at / end_at). Progress is tracked in UserQuest rows.
 */
class Quest extends Model
{
    protected $fillable = [
        'slug', 'title', 'description', 'icon', 'metric',
        'target', 'xp_reward', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function userQuests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserQuest::class);
    }
}