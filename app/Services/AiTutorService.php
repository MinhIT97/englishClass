<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * AI Tutor service — gives personalized learning assistance.
 *
 * 3 entry points:
 *  - ask(): free-form question, with conversation memory (last 5 turns).
 *  - explain(): explain why an answer was wrong in a quiz.
 *  - suggestNext(): recommend the next thing to study based on the
 *    user's recent mistakes + target_band.
 *
 * Uses the same Gemini API as the rest of the app. The conversation
 * history is kept short to bound prompt size and cost.
 */
class AiTutorService
{
    private const HISTORY_KEY = 'ai_tutor:history:%s';
    private const HISTORY_MAX = 5;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.key');
        $this->model  = (string) config('services.gemini.model', 'gemini-1.5-flash');
    }

    private string $apiKey;
    private string $model;

    /**
     * SECURITY (SEC-036): Sanitize any user-supplied string before it is
     * embedded into a Gemini prompt. Strips HTML/script tags and bounds
     * length to prevent prompt-injection via oversized payloads.
     */
    private function sanitizeForPrompt(string $value, int $limit = 1000): string
    {
        $cleaned = strip_tags($value);
        return Str::limit(trim($cleaned), $limit, '…');
    }

    public function ask(User $user, string $question): string
    {
        $history = $this->getHistory($user);
        $history[] = ['role' => 'user', 'text' => $this->sanitizeForPrompt($question)];

        $systemPrompt = $this->systemPrompt($user);

        $answer = $this->callGemini($systemPrompt, $history) ??
            'Xin lỗi, AI tutor đang bận. Vui lòng thử lại sau.';

        $history[] = ['role' => 'model', 'text' => $answer];
        $this->saveHistory($user, $history);

        return $answer;
    }

    public function explain(User $user, string $question, string $userAnswer, string $correctAnswer): string
    {
        $safeQuestion = $this->sanitizeForPrompt($question);
        $safeUserAnswer = $this->sanitizeForPrompt($userAnswer);
        $safeCorrectAnswer = $this->sanitizeForPrompt($correctAnswer);

        $prompt = "Người học trả lời: \"{$safeUserAnswer}\"\n"
                . "Đáp án đúng: \"{$safeCorrectAnswer}\"\n"
                . "Câu hỏi: \"{$safeQuestion}\"\n\n"
                . "Giải thích ngắn gọn (≤120 từ) bằng tiếng Việt tại sao đáp án đúng là \"{$safeCorrectAnswer}\", "
                . "và gợi ý cách tránh sai lần sau. Có thể kèm ví dụ minh hoạ.";

        $systemPrompt = "Bạn là gia sư IELTS thân thiện, giải thích ngắn gọn, dễ hiểu.";

        return $this->callGemini($systemPrompt, [
            ['role' => 'user', 'text' => $prompt],
        ]) ?? 'Không thể giải thích lúc này.';
    }

    public function suggestNext(User $user, array $recentMistakes): string
    {
        if (! count($recentMistakes)) {
            return 'Bạn đang làm rất tốt! Hãy thử một bài Mock Test để đánh giá năng lực.';
        }

        $list = implode("\n", array_map(
            fn ($m) => "- {$this->sanitizeForPrompt((string) ($m['skill'] ?? ''), 60)}: "
                     . "{$this->sanitizeForPrompt((string) ($m['topic'] ?? ''), 100)} "
                     . "(sai {$this->sanitizeForPrompt((string) ($m['wrong_count'] ?? ''), 20)} lần)",
            array_slice($recentMistakes, 0, 5),
        ));

        $prompt = "Dựa trên các lỗi gần đây:\n{$list}\n\n"
                . "Gợi ý 1 bài học/bài tập cụ thể (≤80 từ) để cải thiện. "
                . "Nêu rõ kỹ năng, chủ đề, và cách luyện tập.";

        return $this->callGemini(
            'Bạn là AI coach IELTS. Đưa ra lời khuyên ngắn gọn, actionable.',
            [['role' => 'user', 'text' => $prompt]],
        ) ?? 'Hãy luyện tập thêm các chủ đề bạn hay sai.';
    }

    public function clearHistory(User $user): void
    {
        Cache::forget(sprintf(self::HISTORY_KEY, $user->id));
    }

    private function systemPrompt(User $user): string
    {
        $band = $user->target_band ? "Band mục tiêu: {$user->target_band}" : 'Chưa đặt band mục tiêu';

        return "Bạn là AI Tutor của IELTS Mastery. "
             . "Trả lời bằng tiếng Việt, ngắn gọn, thân thiện. "
             . "Giải thích khi cần, đưa ví dụ minh hoạ. "
             . "{$band}. "
             . "Khuyến khích học viên tự suy nghĩ thay vì đưa đáp án trực tiếp.";
    }

    private function callGemini(string $systemPrompt, array $history): ?string
    {
        if (! $this->apiKey) {
            return null;
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $contents = [];
        foreach ($history as $turn) {
            $contents[] = [
                'role' => $turn['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $turn['text']]],
            ];
        }

        $payload = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 600,
            ],
        ];

        try {
            $response = Http::timeout(20)->post($endpoint, $payload);

            if (! $response->successful()) {
                // SECURITY (SEC-049): the Gemini response body is appended
                // verbatim to the URL query string as `?key=...` so it is
                // not unusual for the server to echo the API key in an
                // error message (e.g. "API key not valid: AIza…"). Truncate
                // the body and explicitly replace the in-memory key with a
                // redaction token before logging.
                Log::warning('[AiTutor] Gemini call failed', [
                    'status' => $response->status(),
                    'body' => Str::of((string) $response->body())
                        ->when($this->apiKey !== '', fn ($s) => $s->replace($this->apiKey, '[API_KEY_REDACTED]'))
                        ->limit(300)
                        ->toString(),
                ]);
                return null;
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            return is_string($text) ? trim($text) : null;
        } catch (\Throwable $e) {
            Log::warning('[AiTutor] Gemini exception', [
                'class' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getHistory(User $user): array
    {
        return Cache::get(sprintf(self::HISTORY_KEY, $user->id), []);
    }

    private function saveHistory(User $user, array $history): void
    {
        $history = array_slice($history, -self::HISTORY_MAX * 2);
        Cache::put(sprintf(self::HISTORY_KEY, $user->id), $history, now()->addHour());
    }
}