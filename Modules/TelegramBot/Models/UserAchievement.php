<?php

namespace Modules\TelegramBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that a user has unlocked a given achievement. Created by
 * AchievementService; rendered by TelegramBotCommandService::sendAchievementsList.
 */
class UserAchievement extends Model
{
    protected $table = 'tgb_user_achievements';

    protected $fillable = [
        'user_id',
        'achievement_key',
        'unlocked_at',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}