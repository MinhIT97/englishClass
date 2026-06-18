<?php

namespace Modules\TelegramBot\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight text-to-speech helper that returns a Telegram-friendly
 * audio URL the client can play inline.
 *
 * We deliberately use Google's public Translate TTS endpoint (no API
 * key, no quota, no Python runtime) for the MVP. The endpoint streams
 * an MP3 file; we return the URL itself rather than proxying bytes so
 * Telegram fetches it directly — this keeps latency low and avoids
 * Laravel running as a CDN.
 *
 * Limits to be aware of:
 *   - Google Translate TTS is undocumented but widely used. It works
 *     for short phrases (<200 chars). Longer text will be truncated
 *     or rejected silently.
 *   - Voices are limited: `en-US` (default female), `en-GB`, `en-AU`,
 *     `en-IN`. Pick by audience.
 *   - If the endpoint ever goes away, callers fall back gracefully
 *     because the returned URL is just a string — the absence of a
 *     play button is acceptable degradation.
 *
 * For production scale, replace this with Google Cloud TTS (WaveNet
 * voices) or edge-tts. The interface stays the same.
 */
class TextToSpeechService
{
    /** Default voice for Vietnamese English learners (US English, natural). */
    private const DEFAULT_VOICE = 'en-US';
    private const CALLBACK_PREFIX = 'tgb:listen:';
    private const MAX_CHUNK_LENGTH = 180;
    private const MAX_TEXT_LENGTH = 1200;

    public function callbackData(string $text, string $voice = self::DEFAULT_VOICE): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $token = substr(hash('sha256', $voice . "\0" . $text), 0, 24);
        Cache::put($this->cacheKey($token), [
            'text' => mb_substr($text, 0, self::MAX_TEXT_LENGTH),
            'voice' => $voice,
        ], now()->addHours(48));

        return self::CALLBACK_PREFIX . $token;
    }

    /**
     * @return array{audio: string, text: string}|null
     */
    public function audioForCallback(string $token): ?array
    {
        $payload = Cache::get($this->cacheKey($token));
        if (! is_array($payload) || empty($payload['text'])) {
            return null;
        }

        $audio = $this->audio(
            (string) $payload['text'],
            (string) ($payload['voice'] ?? self::DEFAULT_VOICE)
        );

        return $audio === null ? null : [
            'audio' => $audio,
            'text' => (string) $payload['text'],
        ];
    }

    public function audio(string $text, string $voice = self::DEFAULT_VOICE): ?string
    {
        $text = trim(mb_substr($text, 0, self::MAX_TEXT_LENGTH));
        if ($text === '') {
            return null;
        }

        $audio = '';

        try {
            foreach ($this->chunks($text) as $chunk) {
                $url = $this->url($chunk, $voice);
                if ($url === null) {
                    return null;
                }

                $response = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0',
                        'Accept' => 'audio/mpeg,audio/*;q=0.9,*/*;q=0.1',
                    ])
                    ->get($url);

                if (! $response->successful() || $response->body() === '') {
                    Log::warning('[TelegramBot] Free TTS download failed', [
                        'status' => $response->status(),
                    ]);
                    return null;
                }

                $audio .= $response->body();
            }
        } catch (\Throwable $e) {
            Log::warning('[TelegramBot] Free TTS exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return $audio !== '' ? $audio : null;
    }

    /**
     * Build a play URL for the given text. Returns null on invalid input
     * or if text exceeds the safe length threshold.
     *
     * @param  string  $text    English text to speak
     * @param  string  $voice   one of: en-US, en-GB, en-AU, en-IN
     * @param  float   $speed   0.25 – 4.0 (1.0 = normal)
     */
    public function url(string $text, string $voice = self::DEFAULT_VOICE, float $speed = 1.0): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Google Translate TTS silently drops long text. Truncate up to
        // 200 characters at a word boundary to keep the URL short and
        // the audio clean.
        if (mb_strlen($text) > 200) {
            $truncated = mb_substr($text, 0, 200);
            $lastSpace = mb_strrpos($truncated, ' ');
            if ($lastSpace !== false) {
                $truncated = mb_substr($truncated, 0, $lastSpace);
            }
            $text = $truncated;
        }

        $tl = $this->resolveTl($voice);
        $q = rawurlencode($text);
        $url = "https://translate.google.com/translate_tts"
            . "?ie=UTF-8&q={$q}&tl={$tl}&client=tw-ob";

        // Speed isn't a native param on this endpoint, but many clients
        // append &ttsspeed=N — we add it for clients that honour it.
        if ($speed !== 1.0) {
            $url .= "&ttsspeed=" . number_format($speed, 2, '.', '');
        }

        return $url;
    }

    /**
     * Map voice alias to Google Translate TTS `tl` parameter.
     */
    private function resolveTl(string $voice): string
    {
        return match ($voice) {
            'en-GB' => 'en-GB',
            'en-AU' => 'en-AU',
            'en-IN' => 'en-IN',
            default => 'en-US',
        };
    }

    /**
     * @return list<string>
     */
    private function chunks(string $text): array
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $chunks = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) <= self::MAX_CHUNK_LENGTH) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
            }
            $current = mb_substr($word, 0, self::MAX_CHUNK_LENGTH);
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function cacheKey(string $token): string
    {
        return "tgb:tts:{$token}";
    }
}
