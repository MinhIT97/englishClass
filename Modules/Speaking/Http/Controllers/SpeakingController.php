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
    public function __construct(
        private readonly SpeakingSessionService $speakingSessionService,
        private readonly \Modules\Speaking\Services\AiSpeakingService $aiSpeakingService
    ) {
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

    public function gradeAudio(Request $request)
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:5120'], // 5MB cap
            'reference_text' => ['required', 'string', 'max:1000'],
        ]);

        $audioFile = $request->file('audio');
        $referenceText = $request->input('reference_text');

        // Check if Gemini is configured/live
        if (!$this->aiSpeakingService->isLive()) {
            return response()->json([
                'feedback' => '<p><strong>[MOCK MODE]</strong> Phát âm của bạn rất tốt! Bạn đã phát âm chính xác từ: "' . htmlspecialchars($referenceText) . '". Nhịp điệu và ngữ điệu tự nhiên, tiếp tục phát huy nhé!</p>'
            ]);
        }

        try {
            $audioBytes = file_get_contents($audioFile->getRealPath());
            $audioBase64 = base64_encode($audioBytes);

            $prompt = "You are a professional IELTS Speaking Examiner.\n" .
                      "The user is practicing pronunciation shadowing.\n" .
                      "The reference text they are trying to repeat is: \"{$referenceText}\".\n" .
                      "Compare their spoken audio with this reference text.\n" .
                      "Provide detailed pronunciation feedback in Vietnamese.\n" .
                      "Point out specific words they mispronounced, suggest how to improve their accent, intonation, and rhythm.\n" .
                      "Keep the feedback encouraging and concise (around 100-150 words).\n" .
                      "Output the result as a JSON object with the key 'feedback' containing the HTML formatted feedback.";

            $mimeType = $audioFile->getMimeType();
            $result = $this->aiSpeakingService->generate($prompt, $audioBase64, $mimeType);

            $feedback = $result['feedback'] ?? null;
            if (!$feedback) {
                $feedback = is_string($result) ? $result : 'Không thể chấm điểm phát âm lúc này. Vui lòng thử lại.';
            }

            return response()->json(['feedback' => $feedback]);

        } catch (\Exception $e) {
            Log::error('Pronunciation Shadowing Error', ['error' => $e->getMessage()]);
            return response()->json(['feedback' => 'Lỗi hệ thống khi chấm phát âm: ' . $e->getMessage()], 500);
        }
    }
}
