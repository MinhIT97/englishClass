<?php

namespace Modules\Speaking\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoiceService
{
    protected ?string $apiKey;
    protected string $sttModel = 'gemini-1.5-flash';
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    /**
     * Convert Audio to Text using Gemini 1.5 Flash (STT)
     */
    public function transcribe(string $audioBase64): ?string
    {
        if (!$this->apiKey) return null;

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => 'Please transcribe this audio file exactly into English text. Return ONLY the transcribed text, no other comments.'],
                    ['inline_data' => [
                        'mime_type' => 'audio/webm',
                        'data' => $audioBase64
                    ]]
                ]
            ]]
        ];

        try {
            $url = "{$this->apiUrl}{$this->sttModel}:generateContent?key={$this->apiKey}";
            $response = Http::timeout(15)->post($url, $payload);

            if ($response->failed()) {
                Log::error("STT Failed", ['body' => $response->body()]);
                return null;
            }

            return $response->json('candidates.0.content.parts.0.text');

        } catch (\Exception $e) {
            Log::error("Voice Service STT Exception", ['msg' => $e->getMessage()]);
            return null;
        }
    }
}
