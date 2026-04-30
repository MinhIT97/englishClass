<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiResponseReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public array $message;

    /**
     * Create a new event instance.
     */
    public function __construct(int $userId, array $message)
    {
        $this->userId  = $userId;
        $this->message = $message;
    }

    /**
     * Broadcast on the user's private channel so responses never mix between users.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ai-response.' . $this->userId),
        ];
    }

    /**
     * The data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ai_message'  => $this->message['content'],
            'ai_feedback' => $this->message['ai_feedback'] ?? null,
            'voice_url'   => $this->message['audio_url'] ?? null,
            'message_id'  => $this->message['id'],
        ];
    }
}
