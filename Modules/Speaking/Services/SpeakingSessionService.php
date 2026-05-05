<?php

namespace Modules\Speaking\Services;

use App\Jobs\ProcessAiSpeechJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Speaking\Models\Conversation;
use Modules\Speaking\Models\Message;

class SpeakingSessionService
{
    public function __construct(
        private readonly AiTextService $aiTextService,
        private readonly AiSpeakingService $speakingService,
    ) {
    }

    public function start(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $sessionId = 'sess_' . $user->id . '_' . Str::random(10);
            $openingMessage = 'Hello, I am ready to practice my IELTS speaking.';

            $conversation = Conversation::query()->create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
            ]);

            $reply = $this->aiTextService->generateReply([
                ['role' => 'user', 'content' => $openingMessage],
            ]);

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $openingMessage,
            ]);

            $assistantMessage = Message::query()->create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $reply['reply'],
                'ai_feedback' => null,
            ]);

            $assistantMessage->update([
                'audio_url' => $this->speakingService->generateTTS($reply['reply']),
            ]);

            return [
                'session_id' => $sessionId,
                'ai_message' => $assistantMessage->content,
                'voice_url' => $assistantMessage->audio_url,
            ];
        });
    }

    public function queueMessage(User $user, string $sessionId, ?string $message, ?string $audio): Message
    {
        $conversation = Conversation::query()
            ->forSessionOfUser($sessionId, $user->id)
            ->firstOrFail();

        $userContent = $message;

        if (!$userContent && $audio) {
            $userContent = '[Audio message - awaiting AI transcription]';
        }

        abort_if(!$userContent, 422, 'No message or audio provided.');

        $userMessage = Message::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userContent,
            'ai_feedback' => null,
        ]);

        ProcessAiSpeechJob::dispatch($conversation, $userMessage, $audio);

        return $userMessage;
    }

    public function poll(User $user, string $sessionId, int $afterId = 0): ?Message
    {
        $conversation = Conversation::query()
            ->forSessionOfUser($sessionId, $user->id)
            ->firstOrFail();

        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->assistant()
            ->afterId($afterId)
            ->orderBy('id')
            ->first();
    }
}
