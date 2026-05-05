<?php

namespace Modules\Speaking\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiTextService
{
    protected ?string $apiKey;
    protected string $model = 'gemini-1.5-flash';
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model  = config('services.gemini.model', 'gemini-1.5-flash');
    }

    /**
     * Unified Gemini Text API
     */
    public function generateReply(array $history): array
    {
        if (!$this->apiKey) return ['reply' => 'API Key missing.'];

        $systemPrompt = "You are an English IELTS tutor. Be concise, friendly and professional.";

        $contents = collect($history)->map(fn($msg) => [
            'role' => ($msg['role'] === 'assistant' || $msg['role'] === 'model') ? 'model' : 'user',
            'parts' => [['text' => $msg['content']]]
        ])->values()->toArray();

        $payload = [
            'contents' => $contents,
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'generationConfig' => ['temperature' => 0.7, 'responseMimeType' => 'application/json']
        ];

        try {
            $url = "{$this->apiUrl}{$this->model}:generateContent?key={$this->apiKey}";
            $response = Http::timeout(10)->post($url, $payload);
            $data = json_decode($response->json('candidates.0.content.parts.0.text'), true);

            return [
                'original'    => $data['original'] ?? '',
                'corrected'   => $data['corrected'] ?? '',
                'explanation' => $data['explanation'] ?? '',
                'reply'       => $data['reply'] ?? 'Interesting, tell me more.',
            ];
        } catch (\Exception $e) {
            Log::error("AiTextService Error: " . $e->getMessage());
            return ['reply' => 'I am sorry, something went wrong.'];
        }
    }
}
