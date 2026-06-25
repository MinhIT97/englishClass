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
        // SECURITY (SEC-043): the `audio` field arrives as a Base64-encoded
        // string in the JSON body (not a file upload, so Laravel's mimes:*
        // rule cannot run). Validate format here before forwarding the
        // payload to Gemini — reject anything that decodes to PHP/HTML
        // markers, contains NUL bytes, or exceeds the 5 MB ceiling.
        if (! $this->isSafeAudioBase64($audioBase64)) {
            Log::warning('[VoiceService] Rejected unsafe audio payload', [
                'len' => strlen($audioBase64),
            ]);
            return null;
        }

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
     * Validate a Base64-encoded audio payload. The decoded bytes must:
     *   - be valid Base64 (strict, no whitespace inside the data run),
     *   - decode to at most ~5 MB,
     *   - not contain PHP open tags / NUL bytes / obvious HTML markers.
     *
     * @see SEC-043 — service-layer guard for Base64 audio uploads.
     */
    private function isSafeAudioBase64(string $payload): bool
    {
        // Cap raw length first to avoid spending CPU decoding 100 MB junk.
        if (strlen($payload) === 0 || strlen($payload) > 7_168_000) {
            return false;
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false || $decoded === '') {
            return false;
        }

        if (strlen($decoded) > 5_242_880) { // 5 MiB
            return false;
        }

        // Reject any payload whose decoded bytes look like source code or
        // HTML — audio should be opaque binary. (We intentionally do NOT
        // flag long ASCII runs because legitimate containers — ID3 tags,
        // Vorbis comments, RIFF INFO chunks — routinely carry long
        // text fields like artist/title strings.)
        if (str_contains($decoded, "<?php")
            || str_contains($decoded, "<?=")
            || str_contains($decoded, "<script")
            || str_contains($decoded, "\0")
        ) {
            return false;
        }

        return true;
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
