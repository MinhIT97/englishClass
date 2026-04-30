<?php

namespace Modules\Speaking\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiSpeakingService
{
    protected string $apiKey;
    protected string $model = 'gemini-1.5-pro';
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    protected int $timeout = 15;
    protected int $retryAttempts = 2;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY') ?: config('services.gemini.key');
    }

    public function isLive(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Generate AI response with robust JSON extraction and retry logic.
     */
    public function generateResponse(string $sessionId, array $history): array
    {
        $systemPrompt = "You are an English IELTS tutor. Analyze the user's input. 
        Provide output STRICTLY in JSON format with keys: 'original', 'corrected', 'explanation', 'reply'.
        Ensure 'reply' is a natural follow-up question.
        IMPORTANT: Your entire response must be a single valid JSON object. No markdown tags, no filler text.";

        $contents = collect($history)->map(fn($msg) => [
            'role' => $msg['role'] === 'user' ? 'user' : 'model',
            'parts' => [['text' => $msg['content']]]
        ])->toArray();

        $payload = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'responseMimeType' => 'application/json',
            ]
        ];

        try {
            Log::info("AI Request dispatched", ['session_id' => $sessionId]);

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout($this->timeout)
                ->retry($this->retryAttempts, 1000) // Retry twice with 1s sleep
                ->post($this->apiUrl . $this->model . ':generateContent?key=' . $this->apiKey, $payload);

            if ($response->failed()) {
                Log::error("AI API Error", ['status' => $response->status(), 'body' => $response->body()]);
                return $this->getSafeFallback("API connection error.");
            }

            $rawText = $response->json('candidates.0.content.parts.0.text') ?? '{}';
            return $this->parseAndValidateJson($rawText);

        } catch (\Exception $e) {
            Log::error("AI Service Exception", ['message' => $e->getMessage()]);
            return $this->getSafeFallback("Internal service error.");
        }
    }

    /**
     * Robust JSON extraction using regex to handle malformed AI output.
     */
    protected function parseAndValidateJson(string $text): array
    {
        try {
            // Attempt 1: Direct clean
            $clean = trim(str_replace(['```json', '```'], '', $text));
            $data = json_decode($clean, true);

            // Attempt 2: Regex extraction if direct decode fails
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (preg_match('/\{(?:[^{}]|(?R))*\}/', $text, $matches)) {
                    $data = json_decode($matches[0], true);
                }
            }

            if (!isset($data['reply'])) {
                throw new \Exception("Missing required JSON keys");
            }

            return [
                'original'    => $data['original'] ?? '',
                'corrected'   => $data['corrected'] ?? '',
                'explanation' => $data['explanation'] ?? '',
                'reply'       => $data['reply'],
            ];

        } catch (\Exception $e) {
            Log::warning("AI JSON Parse Failure", ['raw' => $text, 'error' => $e->getMessage()]);
            return $this->getSafeFallback("I'm sorry, I couldn't process that response correctly.");
        }
    }

    /**
     * Isolated TTS logic - can be swapped for OpenAI/Azure easily.
     */
    public function generateTTS(string $text): ?string
    {
        if (empty($text)) return null;

        try {
            $textLimit = Str::limit($text, 200);
            $url = "https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl=en&q=" . urlencode($textLimit);

            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $filename = 'tts_' . time() . '_' . Str::random(8) . '.mp3';
                $publicDir = public_path('listening_audio');

                if (!file_exists($publicDir)) {
                    mkdir($publicDir, 0777, true);
                }

                file_put_contents($publicDir . DIRECTORY_SEPARATOR . $filename, $response->body());
                return '/listening_audio/' . $filename;
            }

            return null;
        } catch (\Exception $e) {
            Log::error("TTS Exception", ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function getSafeFallback(string $errorMessage): array
    {
        return [
            'original'    => '',
            'corrected'   => '',
            'explanation' => $errorMessage,
            'reply'       => "I'm sorry, I'm having a bit of technical trouble. Could you please repeat that?",
        ];
    }
}
