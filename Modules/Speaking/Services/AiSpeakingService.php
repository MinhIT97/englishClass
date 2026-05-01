<?php

namespace Modules\Speaking\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiSpeakingService
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model  = config('services.gemini.model', 'gemini-1.5-flash');
    }

    public function isLive(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Generate content using Gemini 1.5 Flash
     * Supports both text and audio/multimodal inputs
     */
    public function generate(string $prompt, ?string $inlineData = null, string $mimeType = 'audio/webm'): ?array
    {
        if (!$this->isLive()) {
            Log::warning("Gemini API Key is missing. Mocking response.");
            return null;
        }

        try {
            $contents = [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ];

            // Nếu có dữ liệu inline (audio/image)
            if ($inlineData) {
                $contents[0]['parts'][] = [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data'      => $inlineData
                    ]
                ];
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => $contents,
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature'        => 0.7,
                    'topP'               => 0.95,
                    'topK'               => 64,
                    'maxOutputTokens'    => 2048,
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                
                // Gemini có thể trả về text thô hoặc JSON string
                return json_decode($text, true);
            }

            Log::error("Gemini API Error: " . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error("Gemini Generation Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate TTS and save to public storage
     */
    public function generateTTS(string $text): ?string
    {
        if (empty($text)) return null;

        try {
            $url = "https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl=en&q=" . urlencode(Str::limit($text, 200));
            $response = Http::get($url);

            if ($response->successful()) {
                $filename = 'tts_' . time() . '_' . Str::random(10) . '.mp3';
                $path = 'public/tts/' . $filename;
                
                // Lưu file vào disk public
                Storage::put($path, $response->body());
                
                // Trả về URL công khai
                return asset('storage/tts/' . $filename);
            }
            return null;
        } catch (\Exception $e) {
            Log::error("TTS Save Error: " . $e->getMessage());
            return null;
        }
    }
}
