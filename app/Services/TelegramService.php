<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function sendAudio(string $chatId, string $audio, string $caption = ''): array|null
    {
        if (empty($this->token) || $audio === '') {
            return null;
        }

        try {
            $payload = [
                'chat_id' => $chatId,
                'title' => 'English listening',
                'performer' => config('app.name', 'EnglishClass'),
            ];

            if ($caption !== '') {
                $payload['caption'] = $this->truncate($caption, 900);
            }

            $response = Http::timeout(30)
                ->attach('audio', $audio, 'english-listening.mp3')
                ->post("{$this->baseUrl}{$this->token}/sendAudio", $payload);

            if (! $response->successful()) {
                Log::error('[Telegram] sendAudio failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[Telegram] sendAudio exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Send an operational error to the configured admin chat.
     * Duplicate alerts are throttled to avoid flooding the admin.
     */
    public function sendAdminAlert(string $title, array $context = [], ?Throwable $exception = null): bool
    {
        if (empty($this->adminChatId) || empty($this->token)) {
            Log::warning('[Telegram] Admin alert skipped because credentials are missing.', [
                'title' => $title,
            ]);

            return false;
        }

        if ($exception) {
            $context['exception'] = $exception::class;
            $context['error'] = $exception->getMessage();
            $context['location'] = $exception->getFile() . ':' . $exception->getLine();
        }

        $fingerprintContext = $context;
        unset($fingerprintContext['time'], $fingerprintContext['request_id']);
        $fingerprint = sha1($title . json_encode($fingerprintContext));
        $throttleKey = "telegram:admin-alert:{$fingerprint}";

        if (! Cache::add($throttleKey, true, now()->addMinutes(5))) {
            return false;
        }

        $lines = [
            '🚨 <b>' . $this->escapeHtml($title) . '</b>',
            '',
            '🖥 <b>Server:</b> <code>' . $this->escapeHtml((string) gethostname()) . '</code>',
            '🌍 <b>Environment:</b> <code>' . $this->escapeHtml((string) app()->environment()) . '</code>',
            '🕒 <b>Time:</b> <code>' . $this->escapeHtml(now()->toDateTimeString()) . '</code>',
        ];

        foreach ($context as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif ($value === null) {
                $value = 'null';
            }

            $lines[] = '• <b>' . $this->escapeHtml((string) $key) . ':</b> <code>'
                . $this->escapeHtml($this->truncate((string) $value, 700)) . '</code>';
        }

        $response = $this->sendMessage(
            $this->adminChatId,
            $this->truncate(implode("\n", $lines), 3900)
        );

        if ($response === null) {
            Cache::forget($throttleKey);
        }

        return $response !== null;
    }

    /**
     * Edit an existing message text.
     */
    public function editMessageText(string $chatId, int $messageId, string $text, ?array $replyMarkup = null): void
    {
        if (empty($this->token)) {
            return;
        }

        $payload = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            Http::timeout(10)->post("{$this->baseUrl}{$this->token}/editMessageText", $payload);
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
     * Send a chat action (typing indicator, upload_photo, etc.) so the
     * user sees "..." while the bot is processing. Telegram expires this
     * automatically after ~5s — caller should resend for longer ops.
     *
     * Common actions: typing, upload_photo, record_voice, find_location.
     */
    public function sendChatAction(string $chatId, string $action = 'typing'): void
    {
        if (empty($this->token)) {
            return;
        }

        try {
            Http::timeout(5)->post("{$this->baseUrl}{$this->token}/sendChatAction", [
                'chat_id' => $chatId,
                'action' => $action,
            ]);
        } catch (\Throwable $e) {
            // Chat action failures are non-critical — log and continue.
            Log::debug('[Telegram] sendChatAction exception', ['message' => $e->getMessage()]);
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

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function truncate(string $value, int $length): string
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length - 1) . '…';
    }
}
