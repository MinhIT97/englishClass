<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceResponseStream implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $sessionId;
    public string $audioChunk;
    public bool $isFinal;

    public function __construct(string $sessionId, string $audioChunk, bool $isFinal = false)
    {
        $this->sessionId = $sessionId;
        $this->audioChunk = $audioChunk;
        $this->isFinal = $isFinal;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('voice.' . $this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'audio.chunk';
    }
}
