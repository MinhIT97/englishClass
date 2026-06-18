<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Services\TextToSpeechService;
use Tests\TestCase;

class TextToSpeechServiceTest extends TestCase
{
    public function test_it_creates_a_short_callback_and_downloads_mp3_bytes(): void
    {
        Cache::flush();
        Http::fake([
            'https://translate.google.com/translate_tts*' => Http::response(
                'mp3-binary',
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        $service = app(TextToSpeechService::class);
        $callback = $service->callbackData('I would like to schedule a meeting.');

        $this->assertNotNull($callback);
        $this->assertLessThanOrEqual(64, strlen($callback));
        $this->assertStringStartsWith('tgb:listen:', $callback);

        $audio = $service->audioForCallback(substr($callback, strlen('tgb:listen:')));

        $this->assertSame('mp3-binary', $audio['audio']);
        $this->assertSame('I would like to schedule a meeting.', $audio['text']);
    }

    public function test_it_returns_null_for_an_expired_callback(): void
    {
        Cache::flush();

        $this->assertNull(
            app(TextToSpeechService::class)->audioForCallback('missing-token')
        );
    }
}
