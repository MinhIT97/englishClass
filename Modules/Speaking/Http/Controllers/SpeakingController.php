<?php

namespace Modules\Speaking\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Speaking\Services\SpeakingService;
use Modules\Speaking\Models\SpeakingSession;
use Illuminate\Http\Request;

class SpeakingController extends Controller
{
    protected $speakingService;

    public function __construct(SpeakingService $speakingService)
    {
        $this->speakingService = $speakingService;
    }

    /**
     * Show the simulator UI.
     */
    public function index()
    {
        return view('speaking::index');
    }

    /**
     * Start/Initialize a session.
     */
    public function start(Request $request)
    {
        $session = $this->speakingService->startSession(auth()->id());
        $aiResponse = $this->speakingService->getNextResponse($session);

        return response()->json([
            'session_id' => $session->id,
            'ai_message' => $aiResponse->content,
        ]);
    }

    /**
     * Send student input and get AI response.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:speaking_sessions,id',
            'message' => 'required|string',
        ]);

        $session = SpeakingSession::findOrFail($request->session_id);
        
        // Save student's turn
        $session->transcripts()->create([
            'content' => $request->message,
            'feedback' => null,
        ]);

        $aiResponse = $this->speakingService->getNextResponse($session, $request->message);

        return response()->json([
            'ai_message' => $aiResponse ? $aiResponse->content : 'Thinking...',
            'feedback' => $aiResponse ? $aiResponse->feedback : null,
        ]);
    }
}
