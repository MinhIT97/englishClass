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
