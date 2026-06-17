<?php

namespace Modules\TelegramBot\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Registers the bot's webhook URL with Telegram.
 *
 * Usage:
 *   php artisan tgb:set-webhook                 # uses TELEGRAM_WEBHOOK_URL
 *   php artisan tgb:set-webhook --url=https://example.com/telegram/webhook
 *   php artisan tgb:set-webhook --drop          # removes the webhook
 */
class SetWebhookCommand extends Command
{
    protected $signature = 'tgb:set-webhook
                            {--url= : Override TELEGRAM_WEBHOOK_URL}
                            {--drop : Remove the current webhook instead of setting one}';

    protected $description = 'Register or remove the Telegram bot webhook.';

    public function handle(): int
    {
        $token = (string) config('telegram.bot_token', '');
        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');
            return self::FAILURE;
        }

        $base = rtrim((string) config('telegram.base_url', 'https://api.telegram.org/bot'), '/');

        if ($this->option('drop')) {
            return $this->callApi("{$base}{$token}/deleteWebhook", [], 'Webhook removed.');
        }

        $url = (string) ($this->option('url') ?: config('telegram.webhook_url', ''));
        if ($url === '') {
            $this->error('No URL provided. Set TELEGRAM_WEBHOOK_URL or pass --url=https://...');
            return self::FAILURE;
        }

        $secret = (string) config('telegram.webhook_secret', '');
        $payload = [
            'url' => $url,
            'allowed_updates' => json_encode(['message', 'edited_message', 'callback_query']),
            'drop_pending_updates' => false,
        ];
        if ($secret !== '') {
            $payload['secret_token'] = $secret;
        }

        return $this->callApi(
            "{$base}{$token}/setWebhook",
            $payload,
            "Webhook set to {$url}."
        );
    }

    private function callApi(string $endpoint, array $payload, string $success): int
    {
        try {
            $response = Http::timeout(15)->post($endpoint, $payload);
        } catch (\Throwable $e) {
            $this->error('HTTP error: ' . $e->getMessage());
            Log::error('[tgb:set-webhook] ' . $e->getMessage());
            return self::FAILURE;
        }

        $body = $response->json();
        if ($response->successful() && ($body['ok'] ?? false)) {
            $this->info($success);
            if (isset($body['result'])) {
                $this->line('Result: ' . json_encode($body['result']));
            }
            return self::SUCCESS;
        }

        $this->error('Telegram rejected the request: ' . $response->body());
        Log::error('[tgb:set-webhook] failed', ['body' => $response->body()]);
        return self::FAILURE;
    }
}
