<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Services\TelegramBotCommandService;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private TelegramService $telegram,
        private TelegramBotCommandService $bot,
    ) {}

    public function handle(Request $request): Response
    {
        try {
            // Xác thực secret token để tránh request giả mạo
            $secret = config('telegram.webhook_secret');
            if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
                Log::warning('[Telegram Webhook] Unauthorized request');
                return response('Unauthorized', 401);
            }

            $payload = $request->all();

            // 1) Admin callback queries: approve_user_{id} / reject_user_{id}
            if (isset($payload['callback_query'])) {
                $cb = $payload['callback_query'];
                $data = (string) ($cb['data'] ?? '');

                if (preg_match('/^(approve|reject)_user_(\d+)$/', $data, $matches)) {
                    $this->dispatchAdminCallback($matches[1], (int) $matches[2], $cb);
                    return response('OK', 200);
                }

                // 2) Anything else (tgb:*) belongs to the learning bot.
                $this->bot->handleCallback(
                    (string) ($cb['message']['chat']['id'] ?? ''),
                    (string) ($cb['id'] ?? ''),
                    $data,
                    $cb['message']['message_id'] ?? null,
                    $this->bot->resolveUser((string) ($cb['message']['chat']['id'] ?? '')),
                );
                return response('OK', 200);
            }

            // 3) Plain message: delegate entirely to the bot command service.
            $message = $payload['message'] ?? $payload['edited_message'] ?? null;
            if ($message && isset($message['chat']['id'], $message['text'])) {
                $chatId = (string) $message['chat']['id'];
                $text = trim((string) $message['text']);
                $username = $message['from']['username'] ?? null;
                $user = $this->bot->resolveUser($chatId);

                if (str_starts_with($text, '/')) {
                    $parts = explode(' ', $text);
                    $cmdPart = ltrim($parts[0], '/');
                    $cmd = explode('@', $cmdPart)[0];
                    $args = trim(implode(' ', array_slice($parts, 1)));
                    $this->bot->handleCommand($chatId, $cmd, $args, $username, $user);
                } else {
                    // Free text - let the command service route it (onboarding wizard fallback,
                    // quiz answer text input, etc.).
                    $this->bot->handleFreeText($chatId, $text, $user);
                }
            }

            return response('OK', 200);
        } catch (\Throwable $e) {
            Log::error('[Telegram Webhook] Unhandled exception', [
                'update_id' => $request->input('update_id'),
                'exception' => $e,
            ]);
            $this->telegram->sendAdminAlert('Telegram webhook exception', [
                'feature' => 'telegram_webhook',
                'update_id' => $request->input('update_id'),
                'chat_id' => $request->input('message.chat.id')
                    ?? $request->input('callback_query.message.chat.id'),
                'command' => $request->input('message.text')
                    ?? $request->input('callback_query.data'),
            ], $e);

            // Telegram requires 200 to avoid retrying the same broken update forever.
            return response('OK', 200);
        }
    }

    private function dispatchAdminCallback(string $action, int $userId, array $cb): void
    {
        $callbackId = (string) ($cb['id'] ?? '');
        $chatId = isset($cb['message']['chat']['id']) ? (string) $cb['message']['chat']['id'] : null;
        $messageId = $cb['message']['message_id'] ?? null;
        $adminName = $cb['from']['first_name'] ?? 'Admin';

        if ($action === 'approve') {
            $this->approveUser($userId, $callbackId, $chatId, $messageId, $adminName);
        } else {
            $this->rejectUser($userId, $callbackId, $chatId, $messageId, $adminName);
        }
    }

    private function approveUser(int $userId, string $callbackId, ?string $chatId, ?int $messageId, string $adminName): void
    {
        $user = User::find($userId);

        if (!$user) {
            $this->telegram->answerCallbackQuery($callbackId, '❌ Học sinh không tồn tại!');
            return;
        }

        if ($user->status === 'active') {
            $this->telegram->answerCallbackQuery($callbackId, 'ℹ️ Học sinh này đã được duyệt trước đó.');
            return;
        }

        $user->update(['status' => 'active']);

        $this->telegram->answerCallbackQuery($callbackId, '✅ Đã duyệt thành công!');

        if ($chatId && $messageId) {
            $approvedAt = now()->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i');
            $this->telegram->editMessageText(
                (string) $chatId,
                $messageId,
                "✅ <b>Đã duyệt học viên</b>\n\n"
                    . "👤 Tên: <b>{$user->name}</b>\n"
                    . "📧 Email: <b>{$user->email}</b>\n\n"
                    . "👨‍💼 Duyệt bởi: <b>{$adminName}</b>\n"
                    . "🕐 Thời gian: <b>{$approvedAt}</b>",
                ['inline_keyboard' => []]
            );
        }

        Log::info("[Telegram] Admin duyệt học viên #{$userId} ({$user->email})");
    }

    private function rejectUser(int $userId, string $callbackId, ?string $chatId, ?int $messageId, string $adminName): void
    {
        $user = User::find($userId);

        if (!$user) {
            $this->telegram->answerCallbackQuery($callbackId, '❌ Học sinh không tồn tại!');
            return;
        }

        if ($user->status === 'rejected') {
            $this->telegram->answerCallbackQuery($callbackId, 'ℹ️ Học sinh này đã bị từ chối trước đó.');
            return;
        }

        $user->update(['status' => 'rejected']);

        $this->telegram->answerCallbackQuery($callbackId, '❌ Đã từ chối học viên.');

        if ($chatId && $messageId) {
            $rejectedAt = now()->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i');
            $this->telegram->editMessageText(
                (string) $chatId,
                $messageId,
                "❌ <b>Đã từ chối học viên</b>\n\n"
                    . "👤 Tên: <b>{$user->name}</b>\n"
                    . "📧 Email: <b>{$user->email}</b>\n\n"
                    . "👨‍💼 Từ chối bởi: <b>{$adminName}</b>\n"
                    . "🕐 Thời gian: <b>{$rejectedAt}</b>",
                ['inline_keyboard' => []]
            );
        }

        Log::info("[Telegram] Admin từ chối học viên #{$userId} ({$user->email})");
    }
}
