<?php

namespace Modules\Speaking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAiSpeechJob;
use Modules\Speaking\Models\Conversation;
use Modules\Speaking\Models\Message;
use Modules\Speaking\Services\AiSpeakingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpeakingController extends Controller
{
    protected AiSpeakingService $aiService;

    public function __construct(AiSpeakingService $aiService)
    {
        $this->aiService = $aiService;
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
     * Returns the session_id and initial AI greeting (processed synchronously for speed).
     */
    public function start(Request $request)
    {
        try {
            $user = auth()->user();
            $sessionId = 'sess_' . $user->id . '_' . Str::random(10);

            // Create isolated conversation for this user
            $conversation = Conversation::create([
                'user_id'    => $user->id,
                'session_id' => $sessionId,
            ]);

            // Build greeting prompt
            $history = [
                ['role' => 'user', 'content' => 'Hello, I am ready to practice my IELTS speaking.']
            ];

            $aiResponse = $this->aiService->generateResponse($sessionId, $history);

            // Save the user's opening message
            Message::create([
                'conversation_id' => $conversation->id,
                'role'            => 'user',
                'content'         => 'Hello, I am ready to practice my IELTS speaking.',
            ]);

            // Save AI greeting
            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role'            => 'assistant',
                'content'         => $aiResponse['reply'],
                'ai_feedback'     => null, // No feedback for greeting
            ]);

            // Generate TTS for the greeting
            $voiceUrl = $this->aiService->generateTTS($aiResponse['reply']);
            $assistantMessage->update(['audio_url' => $voiceUrl]);

            Log::info('Speaking session started', ['session_id' => $sessionId, 'user_id' => $user->id]);

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

    /**
     * Receive user audio/text and dispatch AI job for async processing.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|exists:conversations,session_id',
            'message'    => 'nullable|string|max:2000',
            'audio'      => 'nullable|string', // Base64 audio
        ]);

        $conversation = Conversation::where('session_id', $request->session_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $userContent = $request->message;

        // If audio is sent, store the placeholder; AI will transcribe in the Job
        if (!$userContent && $request->audio) {
            $userContent = '[Audio message – awaiting AI transcription]';
        }

        if (!$userContent) {
            return response()->json(['error' => 'No message or audio provided.'], 422);
        }

        // Store user message immediately
        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $userContent,
            'ai_feedback'     => null,
        ]);

        // Dispatch async job — do NOT block the HTTP response
        ProcessAiSpeechJob::dispatch($conversation, $userMessage, $request->audio);

        Log::info('AI Speech Job Dispatched', [
            'session_id' => $request->session_id,
            'user_id'    => auth()->id(),
            'message_id' => $userMessage->id,
        ]);

        return response()->json([
            'status'     => 'processing',
            'message_id' => $userMessage->id,
        ], 202);
    }

    /**
     * Poll for the latest AI response after a given message ID.
     * Frontend calls this every 2s after dispatching a chat job.
     */
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
