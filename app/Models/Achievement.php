<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    public const RARITY_COMMON = 'common';
    public const RARITY_RARE = 'rare';
    public const RARITY_LEGENDARY = 'legendary';

    protected $fillable = [
        'slug', 'title', 'description', 'icon', 'rarity', 'xp_reward',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('earned_at');
    }
}