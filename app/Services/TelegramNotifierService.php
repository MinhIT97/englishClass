<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifierService
{
    protected string $token;
    protected string $chatId;
    protected string $baseUrl;

    public function __construct()
    {
        $this->token = (string) config('services.telegram.bot_token', '');
        $this->chatId = (string) config('services.telegram.chat_id', '');
        $this->baseUrl = rtrim((string) config('services.telegram.base_url', 'https://api.telegram.org/bot'), '/');
    }

    public function sendDeployNotification(array $data): bool
    {
        if ($this->token === '' || $this->chatId === '') {
            Log::warning('Telegram deploy notification skipped because credentials are missing.');
            return false;
        }

        $statusEmoji = $data['status'] === 'success' ? '🚀' : '❌';
        $statusText = strtoupper($data['status']);
        $healthDetails = '';

        foreach ($data['health'] ?? [] as $key => $status) {
            $healthDetails .= "\n* {$key}: " . strtoupper((string) $status);
        }

        $message = "{$statusEmoji} *Deploy {$statusText}*\n"
            . "--------------------------\n"
            . "📍 *Server:* " . gethostname() . "\n"
            . "🌿 *Branch:* " . ($data['branch'] ?? 'N/A') . "\n"
            . "🆔 *Commit:* " . ($data['commit'] ?? 'N/A') . "\n"
            . "🕒 *Time:* " . now()->toDateTimeString() . "\n"
            . "\n*Health Check:*"
            . ($healthDetails ?: "\n(No data)") . "\n";

        try {
            $response = Http::post("{$this->baseUrl}/{$this->token}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            if ($response->failed()) {
                Log::error('Telegram Notification Failed', ['body' => $response->body()]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Telegram Exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text): bool
    {
        try {
            Http::post("{$this->baseUrl}/{$this->token}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Telegram answerCallbackQuery Error: ' . $e->getMessage());
            return false;
        }
    }

    public function editMessageText(int $chatId, int $messageId, string $text): bool
    {
        try {
            Http::post("{$this->baseUrl}/{$this->token}/editMessageText", [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => ['inline_keyboard' => []],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Telegram editMessageText Error: ' . $e->getMessage());
            return false;
        }
    }
}
