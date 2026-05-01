<?php

namespace Modules\Speaking\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoiceService
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model  = config('services.gemini.model', 'gemini-1.5-flash');
    }

    /**
     * STT using Gemini Multimodal (Direct & Fast)
     */
    public function stt(string $audioBase64): ?string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key=" . $this->apiKey;
        $payload = [
            'contents' => [['parts' => [
                ['text' => 'Transcribe this audio exactly.'],
                ['inline_data' => ['mime_type' => 'audio/webm', 'data' => $audioBase64]]
            ]]]
        ];

        try {
            $res = Http::post($url, $payload);
            return $res->json('candidates.0.content.parts.0.text');
        } catch (\Exception $e) { return null; }
    }

    /**
     * TTS: Returns Base64 Data URI
     */
    public function tts(string $text): ?string
    {
        try {
            $url = "https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl=en&q=" . urlencode(substr($text, 0, 200));
            $res = Http::get($url);
            return $res->successful() ? base64_encode($res->body()) : null;
        } catch (\Exception $e) { return null; }
    }
}
