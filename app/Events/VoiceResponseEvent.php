<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceResponseEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sessionId;
    public $textChunk;
    public $audioChunk;

    /**
     * Create a new event instance.
     */
    public function __construct($sessionId, $textChunk = null, $audioChunk = null)
    {
        $this->sessionId = $sessionId;
        $this->textChunk = $textChunk;
        $this->audioChunk = $audioChunk;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('speaking-session.' . $this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'VoiceResponseArrived';
    }
}
