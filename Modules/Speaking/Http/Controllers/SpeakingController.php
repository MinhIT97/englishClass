<?php

namespace Modules\Speaking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Speaking\Http\Requests\ChatSpeakingRequest;
use Modules\Speaking\Http\Requests\PollSpeakingRequest;
use Modules\Speaking\Services\SpeakingSessionService;

class SpeakingController extends Controller
{
    public function __construct(private readonly SpeakingSessionService $speakingSessionService)
    {
    }

    public function index(Request $request)
    {
        return view('speaking::index', [
            'returnTo' => $request->query('return_to'),
            'setLabel' => $request->query('set'),
            'sectionLabel' => $request->query('section'),
        ]);
    }

    public function start(Request $request)
    {
        try {
            return response()->json($this->speakingSessionService->start($request->user()));
        } catch (\Exception $e) {
            Log::error('Speaking Start Error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not start session: ' . $e->getMessage()], 500);
        }
    }

    public function chat(ChatSpeakingRequest $request)
    {
        $userMessage = $this->speakingSessionService->queueMessage(
            $request->user(),
            $request->validated('session_id'),
            $request->validated('message'),
            $request->validated('audio'),
        );

        return response()->json([
            'status' => 'processing',
            'message_id' => $userMessage->id,
        ], 202);
    }

    public function poll(PollSpeakingRequest $request)
    {
        $message = $this->speakingSessionService->poll(
            $request->user(),
            $request->validated('session_id'),
            (int) ($request->validated('after') ?? 0),
        );

        if (!$message) {
            return response()->json(['message' => null]);
        }

        return response()->json([
            'message' => [
                'id' => $message->id,
                'ai_message' => $message->content,
                'ai_feedback' => $message->ai_feedback,
                'voice_url' => $message->audio_url,
            ],
        ]);
    }
}
