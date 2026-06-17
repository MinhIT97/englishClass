<?php

namespace Modules\TelegramBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTelegramLink extends Model
{
    protected $table = 'tgb_user_telegram_links';

    protected $fillable = [
        'user_id',
        'telegram_chat_id',
        'telegram_username',
        'linked_at',
        'last_interaction_at',
    ];

    protected $casts = [
        'linked_at' => 'datetime',
        'last_interaction_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
