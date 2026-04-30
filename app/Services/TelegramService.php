<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;
    private string $adminChatId;
    private string $baseUrl;

    public function __construct()
    {
        $this->token      = config('telegram.bot_token', '');
        $this->adminChatId = config('telegram.admin_chat_id', '');
        $this->baseUrl    = config('telegram.base_url', 'https://api.telegram.org/bot');
    }

    /**
     * Send a message via Telegram Bot API.
     */
    public function sendMessage(string $chatId, string $text, array $replyMarkup = []): array|null
    {
        if (empty($this->token)) {
            Log::warning('[Telegram] BOT_TOKEN chưa được cấu hình.');
            return null;
        }

        $payload = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        if (!empty($replyMarkup)) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            $response = Http::timeout(10)
                ->post("{$this->baseUrl}{$this->token}/sendMessage", $payload);

            if (!$response->successful()) {
                Log::error('[Telegram] sendMessage thất bại', ['body' => $response->body()]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[Telegram] sendMessage exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Edit an existing message text.
     */
    public function editMessageText(string $chatId, int $messageId, string $text): void
    {
        if (empty($this->token)) {
            return;
        }

        try {
            Http::timeout(10)->post("{$this->baseUrl}{$this->token}/editMessageText", [
                'chat_id'    => $chatId,
                'message_id' => $messageId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            Log::error('[Telegram] editMessageText exception', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Answer a callback query to stop the loading spinner on Telegram.
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void
    {
        if (empty($this->token)) {
            return;
        }

        try {
            Http::timeout(10)->post("{$this->baseUrl}{$this->token}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
                'text'              => $text,
                'show_alert'        => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Telegram] answerCallbackQuery exception', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Send a student registration approval request to admin.
     */
    public function sendStudentApprovalRequest(User $user): void
    {
        if (empty($this->adminChatId)) {
            Log::warning('[Telegram] ADMIN_CHAT_ID chưa được cấu hình.');
            return;
        }

        $registeredAt = $user->created_at
            ? $user->created_at->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i')
            : now()->format('d/m/Y H:i');

        $targetBand = $user->target_band ? "🎯 Target Band: <b>{$user->target_band}</b>" : '🎯 Target Band: <i>Chưa điền</i>';

        $text = "🎓 <b>Học sinh mới đăng ký!</b>\n\n"
            . "👤 Tên: <b>{$user->name}</b>\n"
            . "📧 Email: <b>{$user->email}</b>\n"
            . "{$targetBand}\n"
            . "🕐 Thời gian: <b>{$registeredAt}</b>\n\n"
            . "Vui lòng duyệt học viên này:";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text'          => '✅ Duyệt',
                        'callback_data' => "approve_user_{$user->id}",
                    ],
                    [
                        'text'          => '❌ Từ chối',
                        'callback_data' => "reject_user_{$user->id}",
                    ],
                ],
            ],
        ];

        $this->sendMessage($this->adminChatId, $text, $replyMarkup);
    }
}
