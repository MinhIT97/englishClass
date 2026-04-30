<?php

namespace App\Jobs;

use Modules\Speaking\Models\Conversation;
use Modules\Speaking\Models\Message;
use Modules\Speaking\Services\AiSpeakingService;
use App\Events\AiResponseReady;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessAiSpeechJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60]; // Exponential backoff

    protected Conversation $conversation;
    protected Message $userMessage;
    protected ?string $audioBase64;

    public function __construct(Conversation $conversation, Message $userMessage, ?string $audioBase64 = null)
    {
        $this->conversation = $conversation;
        $this->userMessage  = $userMessage;
        $this->audioBase64  = $audioBase64;
    }

    public function handle(AiSpeakingService $aiService): void
    {
        // 1. Atomic Lock - Prevent duplicate processing for same user/session
        // Lock time reduced to 25s (slightly under job timeout)
        $lockKey = "ai_proc_lock_{$this->conversation->id}";
        $lock = Cache::lock($lockKey, 25);

        if (!$lock->get()) {
            Log::warning("Duplicate job detected - skipping", ['session_id' => $this->conversation->session_id]);
            return;
        }

        try {
            // 2. Optimized History Retrieval (Fixing take(-10) bug)
            // Fetch last 10 messages in chronological order
            $historyMessages = Message::where('conversation_id', $this->conversation->id)
                ->where('id', '<=', $this->userMessage->id)
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get()
                ->reverse();

            $history = $historyMessages->map(function ($msg) {
                $content = $msg->content;
                // Add context for AI if audio was sent
                if ($msg->id === $this->userMessage->id && $this->audioBase64) {
                    $content = "[STUDENT AUDIO ATTACHED] " . $content;
                }
                return ['role' => $msg->role, 'content' => $content];
            })->toArray();

            // 3. Generate Response
            $aiResponse = $aiService->generateResponse($this->conversation->session_id, $history);

            // 4. Update transcription if AI extracted text from audio
            if ($this->audioBase64 && !empty($aiResponse['original'])) {
                $this->userMessage->update(['content' => $aiResponse['original']]);
            }

            // 5. Generate TTS
            $audioUrl = $aiService->generateTTS($aiResponse['reply']);

            // 6. Store AI response
            $assistantMessage = Message::create([
                'conversation_id' => $this->conversation->id,
                'role'            => 'assistant',
                'content'         => $aiResponse['reply'],
                'ai_feedback'     => [
                    'original'    => $aiResponse['original'],
                    'corrected'   => $aiResponse['corrected'],
                    'explanation' => $aiResponse['explanation'],
                ],
                'audio_url'       => $audioUrl,
            ]);

            // 7. Broadcast via Reverb/Pusher
            broadcast(new AiResponseReady($this->conversation->user_id, $assistantMessage->toArray()));

        } catch (\Exception $e) {
            Log::error("Job Processing Failed", [
                'session_id' => $this->conversation->session_id,
                'error'      => $e->getMessage()
            ]);
            throw $e; // Trigger retry
        } finally {
            $lock->release();
        }
    }
}
