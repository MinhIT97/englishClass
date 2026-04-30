<?php

namespace App\Jobs;

use Modules\Speaking\Models\Conversation;
use Modules\Speaking\Models\Message;
use Modules\Speaking\Services\AiTextService;
use Modules\Speaking\Services\AiSpeakingService; // For TTS
use Modules\Speaking\Services\VoiceService;     // For STT
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
    public $backoff = [10, 30, 60];

    protected Conversation $conversation;
    protected Message $userMessage;
    protected ?string $audioBase64;

    public function __construct(Conversation $conversation, Message $userMessage, ?string $audioBase64 = null)
    {
        $this->conversation = $conversation;
        $this->userMessage  = $userMessage;
        $this->audioBase64  = $audioBase64;
    }

    public function handle(AiTextService $aiText, AiSpeakingService $ttsService, VoiceService $voiceService): void
    {
        $lockKey = "ai_proc_lock_{$this->conversation->id}";
        $lock = Cache::lock($lockKey, 35);

        if (!$lock->get()) return;

        try {
            // 1. Chuyển đổi Giọng nói sang Văn bản (STT)
            if ($this->audioBase64) {
                $transcript = $voiceService->transcribe($this->audioBase64);
                
                if ($transcript) {
                    $this->userMessage->update(['content' => $transcript]);
                } else {
                    // Nếu STT thất bại, trả về lỗi cho người dùng
                    $this->userMessage->update(['content' => '(Inaudible voice message)']);
                    $this->sendAssistantReply("I'm sorry, I couldn't understand the audio. Could you please type it or try again?");
                    return;
                }
            }

            // 2. Lấy lịch sử (Văn bản sạch sau STT)
            $history = Message::where('conversation_id', $this->conversation->id)
                ->where('id', '<=', $this->userMessage->id)
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get()
                ->reverse()
                ->map(fn($msg) => ['role' => $msg->role, 'content' => $msg->content])
                ->toArray();

            // 3. Gọi AI Chat Service
            $aiResponse = $aiText->generateReply($history);

            // 4. TTS Phản hồi
            $audioUrl = $ttsService->generateTTS($aiResponse['reply']);

            // 5. Lưu và gửi phản hồi
            $this->sendAssistantReply($aiResponse['reply'], $aiResponse, $audioUrl);

        } catch (\Exception $e) {
            Log::error("Voice Pipeline Error", ['msg' => $e->getMessage()]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    protected function sendAssistantReply(string $text, array $feedback = [], ?string $audioUrl = null)
    {
        $assistantMessage = Message::create([
            'conversation_id' => $this->conversation->id,
            'role'            => 'assistant',
            'content'         => $text,
            'ai_feedback'     => !empty($feedback) ? [
                'original'    => $feedback['original'] ?? '',
                'corrected'   => $feedback['corrected'] ?? '',
                'explanation' => $feedback['explanation'] ?? '',
            ] : null,
            'audio_url'       => $audioUrl,
        ]);

        broadcast(new AiResponseReady($this->conversation->user_id, $assistantMessage->toArray()));
    }
}
