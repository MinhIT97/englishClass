<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceResponseEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionId,
        public string $transcript,
        public array $aiResult
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('voice.' . $this->sessionId)];
    }

    public function broadcastAs(): string
    {
        return 'voice.reply';
    }
}
