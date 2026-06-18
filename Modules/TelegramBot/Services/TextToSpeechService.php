<?php

namespace Modules\TelegramBot\Services;

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
}