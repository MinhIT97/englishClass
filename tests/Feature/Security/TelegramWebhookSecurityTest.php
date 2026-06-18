<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\TestCase;

/**
 * Asserts the Telegram webhook secret middleware:
 *  - rejects requests missing/with-wrong secret
 *  - returns 503 in production when secret is unconfigured
 *  - allows the request through in non-production when no secret
 */
class TelegramWebhookSecurityTest extends TestCase
{
    public function test_missing_secret_header_returns_403(): void
    {
        config(['telegram.webhook_secret' => 'expected-secret-value']);

        $response = $this->post('/telegram/webhook', [
            'update_id' => 1,
            'message' => ['chat' => ['id' => 1], 'text' => '/start'],
        ]);

        $response->assertStatus(403);
    }

    public function test_wrong_secret_returns_403(): void
    {
        config(['telegram.webhook_secret' => 'expected-secret-value']);

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'attacker-guess',
        ])->post('/telegram/webhook', [
            'update_id' => 1,
            'message' => ['chat' => ['id' => 1], 'text' => '/start'],
        ]);

        $response->assertStatus(403);
    }

    public function test_correct_secret_passes_middleware(): void
    {
        config(['telegram.webhook_secret' => 'expected-secret-value']);

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'expected-secret-value',
        ])->post('/telegram/webhook', [
            'update_id' => 999999,
        ]);

        // Controller should respond 200 OK with body "OK".
        $response->assertStatus(200);
        $response->assertSee('OK', false);
    }

    public function test_unconfigured_secret_returns_503_in_production(): void
    {
        config(['telegram.webhook_secret' => '']);
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->post('/telegram/webhook', [
            'update_id' => 1,
        ]);

        $response->assertStatus(503);
    }

    public function test_unconfigured_secret_is_allowed_in_development(): void
    {
        config(['telegram.webhook_secret' => '']);
        $this->app->detectEnvironment(fn () => 'local');

        $response = $this->post('/telegram/webhook', [
            'update_id' => 999999,
        ]);

        $response->assertStatus(200);
    }
}