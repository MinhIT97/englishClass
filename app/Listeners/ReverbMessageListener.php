<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

use Laravel\Reverb\Events\MessageReceived;
use Illuminate\Support\Facades\Redis;

class ReverbMessageListener
{
    /**
     * Handle the event.
     */
    public function handle(MessageReceived $event): void
    {
        $payload = json_decode($event->message, true);
        
        if ($payload && isset($payload['event']) && $payload['event'] === 'client-audio-stream') {
            // Push to Redis List so our VoiceStreamWorker daemon can process it
            // doing an async pop without blocking this Reverb loop
            Redis::rpush('voice_stream_queue', json_encode([
                'session_id' => $payload['data']['session_id'] ?? null,
                'audio' => $payload['data']['audio'], // Base64 chunk
                'is_final' => $payload['data']['is_final'] ?? false,
            ]));
        }
    }
}
