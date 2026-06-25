<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Speaking\Services\AiSpeakingService;

class AIChatController extends Controller
{
    /**
     * SECURITY (SEC-037): Cap message length and history size at the
     * boundary so a single request cannot push arbitrarily large payloads
     * into the Gemini prompt.
     */
    private const MAX_MESSAGE_LENGTH = 4000;
    private const MAX_HISTORY_TURNS = 10;
    private const MAX_TURN_LENGTH = 2000;

    protected $aiService;

    public function __construct(AiSpeakingService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function chat(Request $request)
    {
        // SECURITY (SEC-037): validate + bound input size before it reaches
        // the prompt builder. Reject oversize messages with HTTP 422.
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:' . self::MAX_MESSAGE_LENGTH],
            'action' => ['nullable', 'string', Rule::in(['fix', 'explain', 'natural'])],
            'history' => ['nullable', 'array', 'max:' . self::MAX_HISTORY_TURNS],
            'history.*.role' => ['required_with:history', 'string', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required_with:history', 'string', 'max:' . self::MAX_TURN_LENGTH],
        ]);

        $message = $validated['message'];
        $action = $validated['action'] ?? null;
        $history = $validated['history'] ?? [];

        $prompt = $this->buildPrompt($message, $action, $history);
        $result = $this->aiService->generate($prompt);

        if (!$result) {
            return response()->json([
                'message' => 'Xin lỗi, tôi đang bận một chút. Bạn có thể thử lại sau không?',
                'suggestions' => [
                    ['type' => 'fix', 'label' => 'Sửa câu'],
                    ['type' => 'explain', 'label' => 'Giải nghĩa'],
                    ['type' => 'natural', 'label' => 'Đánh giá tự nhiên'],
                ],
                'next_question' => 'Bạn muốn thử một câu khác không?'
            ]);
        }

        return response()->json($result);
    }

    protected function buildPrompt($message, $action, $history)
    {
        // SECURITY (SEC-036): Defense-in-depth — strip any tags from
        // message and history turns before they are concatenated into
        // the prompt, even though validation has already bounded them.
        $safeMessage = Str::limit(strip_tags((string) $message), self::MAX_MESSAGE_LENGTH, '…');

        $historyContext = "";
        foreach ($history as $chat) {
            $role = $chat['role'] === 'user' ? 'Student' : 'Assistant';
            $turn = Str::limit(strip_tags((string) $chat['content']), self::MAX_TURN_LENGTH, '…');
            $historyContext .= "{$role}: {$turn}\n";
        }

        $actionDesc = "";
        if ($action === 'fix') {
            $actionDesc = "Hãy tập trung vào việc sửa lỗi ngữ pháp cho câu này.";
        } elseif ($action === 'explain') {
            $actionDesc = "Hãy giải thích chi tiết cấu trúc ngữ pháp và từ vựng trong câu này bằng tiếng Việt.";
        } elseif ($action === 'natural') {
            $actionDesc = "Hãy đánh giá xem câu này có tự nhiên không và gợi ý cách nói tự nhiên hơn của người bản xứ.";
        } else {
            $actionDesc = "Hãy trả lời tin nhắn của người dùng một cách thân thiện và sửa lỗi nhẹ nếu có.";
        }

        return <<<PROMPT
You are an IELTS English learning assistant.

Conversation History:
{$historyContext}

Current User input: "{$safeMessage}"
Instruction: {$actionDesc}

Return ONLY a JSON response with the following structure:
{
  "message": "your response text",
  "suggestions": [
    { "type": "fix", "label": "Sửa câu" },
    { "type": "explain", "label": "Giải nghĩa" },
    { "type": "natural", "label": "Đánh giá tự nhiên" }
  ],
  "next_question": "a natural follow-up question for the user"
}

Rules:
- Respond in Vietnamese for explanations and follow-up questions.
- Keep the language friendly and encouraging.
- For the 'message' field, if action is 'fix', show the corrected version. If 'explain', provide a clear explanation.
- Ensure the suggestions array does not contain the current action type if an action was provided.
PROMPT;
    }
}
