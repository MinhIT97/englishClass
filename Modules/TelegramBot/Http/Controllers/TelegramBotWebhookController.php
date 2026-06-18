<?php

namespace Modules\TelegramBot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Services\TelegramBotCommandService;

class TelegramBotWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramBotCommandService $commandService,
        private readonly TelegramService $telegram,
    ) {
    }

    public function handle(Request $request)
    {
        // Telegram expects HTTP 200 even for non-message updates (e.g. inline_query)
        // so it doesn't retry forever.
        try {
            $payload = $request->all();

            // Inline button callback.
            if (isset($payload['callback_query'])) {
                $this->handleCallback($payload['callback_query']);
                return response('ok', 200);
            }

            // Plain text or command message.
            $message = $payload['message'] ?? $payload['edited_message'] ?? null;
            if ($message && isset($message['chat']['id'], $message['text'])) {
                $this->handleMessage($message);
                return response('ok', 200);
            }

            return response('ok', 200);
        } catch (\Throwable $e) {
            Log::error('[TelegramBot] Webhook exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->telegram->sendAdminAlert('Telegram webhook exception', [
                'feature' => 'telegram_webhook',
                'update_id' => $request->input('update_id'),
                'chat_id' => $request->input('message.chat.id')
                    ?? $request->input('callback_query.message.chat.id'),
                'command' => $request->input('message.text')
                    ?? $request->input('callback_query.data'),
            ], $e);
            return response('ok', 200);
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = (string) $message['chat']['id'];
        $text = trim((string) $message['text']);
        $username = $message['from']['username'] ?? null;

        $user = $this->commandService->resolveUser($chatId);

        if (str_starts_with($text, '/')) {
            // Strip bot username suffix: "/start@EnglishClassBot" -> "/start"
            $parts = explode(' ', $text);
            $cmdPart = ltrim($parts[0], '/');
            $cmd = explode('@', $cmdPart)[0];
            $args = trim(implode(' ', array_slice($parts, 1)));
            $this->commandService->handleCommand($chatId, $cmd, $args, $username, $user);
            return;
        }

        // Free text - if user has an active conversation state, advance it.
        // For MVP, send a friendly fallback if no state.
        $state = \Modules\TelegramBot\Models\ConversationState::query()
            ->where('telegram_chat_id', $chatId)
            ->whereNotNull('current_command')
            ->first();

        if (! $state) {
            \Log::info('[TelegramBot] Free text from chat, no active state', ['text' => $text]);
        }
        // Future: implement free-text fill-in-blank / word-scramble flows.
    }

    private function handleCallback(array $query): void
    {
        $chatId = (string) ($query['message']['chat']['id'] ?? '');
        $messageId = $query['message']['message_id'] ?? null;
        $callbackId = (string) ($query['id'] ?? '');
        $data = (string) ($query['data'] ?? '');

        $user = $this->commandService->resolveUser($chatId);

        $this->commandService->handleCallback($chatId, $callbackId, $data, $messageId, $user);
    }
}
