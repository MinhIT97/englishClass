<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(private TelegramService $telegram)
    {
    }

    public function handle(Request $request): Response
    {
        // Xác thực secret token để tránh request giả mạo
        $secret = config('telegram.webhook_secret');
        if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            Log::warning('[Telegram Webhook] Unauthorized request');
            return response('Unauthorized', 401);
        }

        $payload = $request->all();

        // Chỉ xử lý callback_query (khi admin bấm Inline Button)
        if (!isset($payload['callback_query'])) {
            return response('OK', 200);
        }

        $callbackQuery = $payload['callback_query'];
        $callbackId    = $callbackQuery['id'];
        $callbackData  = $callbackQuery['data'] ?? '';
        $chatId        = $callbackQuery['message']['chat']['id'] ?? null;
        $messageId     = $callbackQuery['message']['message_id'] ?? null;
        $adminName     = $callbackQuery['from']['first_name'] ?? 'Admin';

        // Xử lý: approve_user_{id}
        if (preg_match('/^approve_user_(\d+)$/', $callbackData, $matches)) {
            $userId = (int) $matches[1];
            $this->approveUser($userId, $callbackId, $chatId, $messageId, $adminName);
            return response('OK', 200);
        }

        // Xử lý: reject_user_{id}
        if (preg_match('/^reject_user_(\d+)$/', $callbackData, $matches)) {
            $userId = (int) $matches[1];
            $this->rejectUser($userId, $callbackId, $chatId, $messageId, $adminName);
            return response('OK', 200);
        }

        return response('OK', 200);
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
