<?php

namespace Modules\TelegramBot\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationState extends Model
{
    protected $table = 'tgb_conversation_states';

    protected $fillable = [
        'telegram_chat_id',
        'current_command',
        'state_data',
    ];

    protected $casts = [
        'state_data' => 'array',
    ];

    /**
     * Find or create a state row for the given chat id.
     */
    public static function forChat(string $chatId): self
    {
        return self::firstOrCreate(['telegram_chat_id' => $chatId]);
    }

    public function clear(): void
    {
        $this->current_command = null;
        $this->state_data = null;
        $this->save();
    }
}
