<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Speaking\Services\AiTextService;
use Modules\Speaking\Services\VoiceService;
use Modules\Speaking\Services\VoiceSessionManager;
use App\Events\VoiceResponseEvent;

class ProcessVoiceChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected string $sessionId, protected array $history) {}

    public function handle(VoiceService $voice, AiTextService $ai, VoiceSessionManager $manager)
    {
        // 1. Get Buffer
        $audio = $manager->getAndClear($this->sessionId);
        if (!$audio) return;

        // 2. STT
        $transcript = $voice->stt($audio);
        if (!$transcript) return;

        // 3. AI Reply
        $newHistory = array_merge($this->history, [['role' => 'user', 'content' => $transcript]]);
        $result = $ai->generateReply($newHistory);

        // 4. TTS
        $audioBase64 = $voice->tts($result['reply']);

        // 5. Broadcast
        broadcast(new VoiceResponseEvent($this->sessionId, $transcript, $result, $audioBase64));
    }
}
