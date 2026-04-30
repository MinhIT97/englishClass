<?php

namespace Modules\Speaking\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiTextService
{
    protected ?string $apiKey;
    protected string $model;
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model  = config('services.gemini.model', 'gemini-1.5-flash');
    }

    public function generateReply(array $history): array
    {
        if (!$this->apiKey) return $this->getSafeFallback("API Key missing.");

        // Ép AI cực kỳ nghiêm ngặt về định dạng
        $systemPrompt = "You are an English IELTS tutor. 
        Your response must be a VALID JSON object with EXACTLY these keys:
        'original': the user's input transcribed or repeated.
        'corrected': a better version of the user's input.
        'explanation': why you made those changes.
        'reply': your natural conversation response or next question.
        
        DO NOT include any other text, markdown, or keys.";

        $contents = collect($history)
            ->filter(function ($msg) {
                $content = $msg['content'] ?? '';
                return !str_contains($content, 'Gemini API Error') && 
                       !str_contains($content, 'Voice message not supported yet') &&
                       !empty(trim($content));
            })
            ->map(function ($msg) {
                return [
                    'role' => ($msg['role'] === 'assistant' || $msg['role'] === 'model') ? 'model' : 'user',
                    'parts' => [['text' => (string) $msg['content']]]
                ];
            })
            ->values()
            ->toArray();

        $payload = [
            'contents' => $contents,
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'generationConfig' => [
                'temperature' => 0.6, // Giảm temperature để AI ổn định hơn
                'responseMimeType' => 'application/json',
            ]
        ];

        try {
            $url = "{$this->apiUrl}{$this->model}:generateContent?key={$this->apiKey}";
            $response = Http::timeout(20)->retry(2, 1000)->post($url, $payload);

            if ($response->failed()) {
                return $this->getSafeFallback("Gemini Error: " . $response->status());
            }

            $rawText = $response->json('candidates.0.content.parts.0.text') ?? '{}';
            return $this->parseJson($rawText);

        } catch (\Exception $e) {
            return $this->getSafeFallback("Internal Error: " . $e->getMessage());
        }
    }

    protected function parseJson(string $text): array
    {
        $cleanText = preg_replace('/^```json\s*|```\s*$/i', '', trim($text));
        $data = json_decode($cleanText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $matches)) {
                $data = json_decode($matches[0], true);
            }
        }

        // Logic tự sửa lỗi nếu AI dùng sai tên trường (như trong ảnh bạn gửi)
        $reply = $data['reply'] ?? $data['message'] ?? $data['next_step'] ?? strip_tags($text);

        return [
            'original'    => $data['original'] ?? '',
            'corrected'   => $data['corrected'] ?? '',
            'explanation' => $data['explanation'] ?? '',
            'reply'       => (string) $reply,
        ];
    }

    protected function getSafeFallback(string $message): array
    {
        return ['original' => '', 'corrected' => '', 'explanation' => '', 'reply' => $message];
    }
}
