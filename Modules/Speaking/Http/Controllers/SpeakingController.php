<?php

namespace Modules\Speaking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAiSpeechJob;
use Modules\Speaking\Models\Conversation;
use Modules\Speaking\Models\Message;
use Modules\Speaking\Services\AiTextService;
use Modules\Speaking\Services\AiSpeakingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpeakingController extends Controller
{
    protected AiTextService $aiText;
    protected AiSpeakingService $ttsService;

    public function __construct(AiTextService $aiText, AiSpeakingService $ttsService)
    {
        $this->aiText = $aiText;
        $this->ttsService = $ttsService;
    }

    /**
     * Show the simulator UI.
     */
    public function index()
    {
        return view('speaking::index');
    }

    /**
     * Start a new conversation session.
     */
    public function start(Request $request)
    {
        try {
            $user = auth()->user();
            $sessionId = 'sess_' . $user->id . '_' . Str::random(10);

            $conversation = Conversation::create([
                'user_id'    => $user->id,
                'session_id' => $sessionId,
            ]);

            $history = [
                ['role' => 'user', 'content' => 'Hello, I am ready to practice my IELTS speaking.']
            ];

            // Gọi đúng phương thức generateReply của AiTextService
            $aiResponse = $this->aiText->generateReply($history);

            Message::create([
                'conversation_id' => $conversation->id,
                'role'            => 'user',
                'content'         => 'Hello, I am ready to practice my IELTS speaking.',
            ]);

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role'            => 'assistant',
                'content'         => $aiResponse['reply'],
                'ai_feedback'     => null,
            ]);

            // Sử dụng ttsService để tạo giọng nói
            $voiceUrl = $this->ttsService->generateTTS($aiResponse['reply']);
            $assistantMessage->update(['audio_url' => $voiceUrl]);

            return response()->json([
                'session_id' => $sessionId,
                'ai_message' => $aiResponse['reply'],
                'voice_url'  => $voiceUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Speaking Start Error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not start session: ' . $e->getMessage()], 500);
        }
    }

    public function chat(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|exists:conversations,session_id',
            'message'    => 'nullable|string|max:2000',
            'audio'      => 'nullable|string', 
        ]);

        $conversation = Conversation::where('session_id', $request->session_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $userContent = $request->message;

        if (!$userContent && $request->audio) {
            $userContent = '[Audio message – awaiting AI transcription]';
        }

        if (!$userContent) {
            return response()->json(['error' => 'No message or audio provided.'], 422);
        }

        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $userContent,
            'ai_feedback'     => null,
        ]);

        ProcessAiSpeechJob::dispatch($conversation, $userMessage, $request->audio);

        return response()->json([
            'status'     => 'processing',
            'message_id' => $userMessage->id,
        ], 202);
    }

    public function poll(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|exists:conversations,session_id',
            'after'      => 'nullable|integer',
        ]);

        $conversation = Conversation::where('session_id', $request->session_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $afterId = (int) ($request->after ?? 0);

        $message = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('id', '>', $afterId)
            ->orderBy('id', 'asc')
            ->first();

        if (!$message) {
            return response()->json(['message' => null]);
        }

        return response()->json([
            'message' => [
                'id'          => $message->id,
                'ai_message'  => $message->content,
                'ai_feedback' => $message->ai_feedback,
                'voice_url'   => $message->audio_url,
            ]
        ]);
    }
}
