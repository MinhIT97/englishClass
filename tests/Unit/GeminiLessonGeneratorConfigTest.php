<?php

namespace Tests\Unit;

use Modules\TelegramBot\Services\GeminiLessonGenerator;
use ReflectionClass;
use Tests\TestCase;

class GeminiLessonGeneratorConfigTest extends TestCase
{
    public function test_it_reads_multiple_api_keys_and_fallback_models_from_config(): void
    {
        config()->set('services.gemini.keys', ' key-one, key-two, key-one ');
        config()->set('services.gemini.model', 'gemini-primary');
        config()->set('services.gemini.fallback_models', 'gemini-backup-a, gemini-backup-b');

        $generator = new GeminiLessonGenerator();
        $reflection = new ReflectionClass($generator);

        $this->assertSame(
            ['key-one', 'key-two'],
            $reflection->getProperty('apiKeys')->getValue($generator)
        );
        $this->assertSame(
            ['gemini-backup-a', 'gemini-backup-b'],
            $reflection->getProperty('fallbackModels')->getValue($generator)
        );
    }

    public function test_it_keeps_single_key_configuration_compatible(): void
    {
        config()->set('services.gemini.keys', 'legacy-key');
        config()->set('services.gemini.fallback_models', 'gemini-2.5-flash');

        $generator = new GeminiLessonGenerator();
        $reflection = new ReflectionClass($generator);

        $this->assertSame(
            ['legacy-key'],
            $reflection->getProperty('apiKeys')->getValue($generator)
        );
    }
}
