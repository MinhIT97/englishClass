<?php

namespace Modules\Speaking\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiSpeakingService
{
    protected ?string $apiKey;
    protected string $model;
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model  = config('services.gemini.model', 'gemini-2.5-flash');
    }

    public function isLive(): bool
    {
        return !empty($this->apiKey);
    }

    public function generateTTS(string $text): ?string
    {
        if (empty($text)) return null;
        try {
            $url = "https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl=en&q=" . urlencode(Str::limit($text, 200));
            $response = Http::get($url);
            return $response->successful() ? 'data:audio/mp3;base64,' . base64_encode($response->body()) : null;
        } catch (\Exception $e) { return null; }
    }
}
