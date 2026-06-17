<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\TelegramBot\Models\ConversationState;
use Modules\TelegramBot\Models\LearningProfile;
use Modules\TelegramBot\Models\Topic;
use Modules\TelegramBot\Models\UserPath;
use Modules\TelegramBot\Models\UserTelegramLink;

/**
 * Drives the /start onboarding wizard via inline keyboards.
 */
class TelegramOnboardingService
{
    public function __construct(private readonly TelegramService $telegram)
    {
    }

    /**
     * Entry point: link the chat id to a user (when a code is supplied) and
     * start the wizard. If already linked, send a welcome-back message.
     */
    public function startWizard(string $chatId, ?string $code, ?string $username = null): void
    {
        $existing = UserTelegramLink::query()->where('telegram_chat_id', $chatId)->first();
        if ($existing) {
            $this->telegram->sendMessage(
                $chatId,
                "👋 Chào {$existing->user->name}! Bạn đã liên kết rồi. Gõ /help để xem các lệnh."
            );
            return;
        }

        if (! $code) {
            $this->showLinkInstructions($chatId);
            return;
        }

        $link = \Modules\TelegramBot\Models\LinkingCode::query()
            ->where('code', $code)
            ->first();

        if (! $link || ! $link->isUsable()) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ Mã liên kết không hợp lệ hoặc đã hết hạn. Vui lòng tạo mã mới tại trang cài đặt Telegram trên web."
            );
            return;
        }

        DB::transaction(function () use ($link, $chatId, $username) {
            UserTelegramLink::query()->updateOrCreate(
                ['user_id' => $link->user_id],
                [
                    'telegram_chat_id' => $chatId,
                    'telegram_username' => $username,
                    'linked_at' => Carbon::now(),
                ]
            );

            $link->used_at = Carbon::now();
            $link->save();
        });

        $this->askPurpose($chatId, $link->user);
    }

    public function showLinkInstructions(string $chatId): void
    {
        $text = "👋 <b>Chào mừng đến với EnglishClass Bot!</b>\n\n"
            . "Để bắt đầu, bạn cần liên kết tài khoản:\n"
            . "1️⃣ Đăng nhập trang web\n"
            . "2️⃣ Vào <b>Cài đặt → Telegram</b>\n"
            . "3️⃣ Tạo mã liên kết và gửi mã đó cho bot theo mẫu:\n\n"
            . "<code>/start MA_CUA_BAN</code>";

        $reply = [
            'inline_keyboard' => [
                [
                    ['text' => '🎓 Hướng dẫn sử dụng', 'callback_data' => 'tgb:help'],
                ],
            ],
        ];
        $this->telegram->sendMessage($chatId, $text, $reply);
    }

    public function askPurpose(string $chatId, User $user): void
    {
        $state = ConversationState::forChat($chatId);
        $state->current_command = 'onboarding';
        $state->state_data = ['step' => 'purpose', 'user_id' => $user->id];
        $state->save();

        $text = "🎯 <b>Bước 1/3:</b> Bạn muốn học tiếng Anh với mục đích gì?";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎓 IELTS', 'callback_data' => 'tgb:onb:purpose:ielts'],
                    ['text' => '💬 Giao tiếp', 'callback_data' => 'tgb:onb:purpose:daily'],
                ],
                [
                    ['text' => '💼 Công việc', 'callback_data' => 'tgb:onb:purpose:business'],
                ],
            ],
        ];
        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    public function handlePurpose(string $chatId, string $purpose, int $userId): void
    {
        $state = ConversationState::forChat($chatId);
        $state->state_data = array_merge((array) $state->state_data, ['purpose' => $purpose]);
        $state->save();

        $user = User::find($userId);
        $suggested = (new LearningProfile())->suggestLevel($user?->target_band);

        $text = "📊 <b>Bước 2/3:</b> Trình độ hiện tại của bạn?";
        if ($suggested && $user?->target_band) {
            $text .= "\n<i>(Gợi ý theo target_band {$user->target_band}: " . LearningProfile::levels()[$suggested] . ')</i>';
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌱 Cơ bản', 'callback_data' => 'tgb:onb:level:beginner'],
                    ['text' => '📗 Trung cấp', 'callback_data' => 'tgb:onb:level:intermediate'],
                ],
                [
                    ['text' => '🚀 Nâng cao', 'callback_data' => 'tgb:onb:level:advanced'],
                ],
            ],
        ];
        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    public function handleLevel(string $chatId, string $level, int $userId): void
    {
        $state = ConversationState::forChat($chatId);
        $state->state_data = array_merge((array) $state->state_data, ['level' => $level]);
        $state->save();

        $text = "⏰ <b>Bước 3/3:</b> Bạn muốn nhận bài học lúc mấy giờ?";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌅 7:00', 'callback_data' => 'tgb:onb:hour:7'],
                    ['text' => '☀️ 12:00', 'callback_data' => 'tgb:onb:hour:12'],
                ],
                [
                    ['text' => '🌙 19:00', 'callback_data' => 'tgb:onb:hour:19'],
                    ['text' => '🌃 21:00', 'callback_data' => 'tgb:onb:hour:21'],
                ],
            ],
        ];
        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    public function handleHour(string $chatId, int $hour, int $userId): void
    {
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;

        $profile = LearningProfile::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'purpose' => $data['purpose'] ?? LearningProfile::PURPOSE_IELTS,
                'level' => $data['level'] ?? LearningProfile::LEVEL_INTERMEDIATE,
                'daily_send_hour' => $hour,
                'timezone' => 'Asia/Ho_Chi_Minh',
                'onboarded_at' => Carbon::now(),
            ]
        );

        $this->seedUserPath($userId, $profile);

        $state->clear();

        $summary = "✅ <b>Hoàn tất thiết lập!</b>\n\n"
            . "🎯 Mục đích: " . LearningProfile::purposes()[$profile->purpose] . "\n"
            . "📊 Trình độ: " . LearningProfile::levels()[$profile->level] . "\n"
            . "⏰ Giờ nhận bài: {$hour}:00\n\n"
            . "Bạn có thể đổi lại bất cứ lúc nào với /settings.\n"
            . "Gõ /help để xem danh sách lệnh.";

        $this->telegram->sendMessage($chatId, $summary);
    }

    private function seedUserPath(int $userId, LearningProfile $profile): void
    {
        $topics = Topic::query()
            ->where('purpose', $profile->purpose)
            ->where('is_active', true)
            ->orderBy('order_index')
            ->get();

        foreach ($topics as $i => $topic) {
            UserPath::query()->updateOrCreate(
                ['user_id' => $userId, 'topic_id' => $topic->id],
                [
                    'status' => $i === 0 ? UserPath::STATUS_CURRENT : UserPath::STATUS_LOCKED,
                    'started_at' => $i === 0 ? Carbon::now() : null,
                    'word_count_target' => 5,
                ]
            );
        }
    }

    /**
     * Generate a fresh 8-char alphanumeric linking code for a web user.
     */
    public static function generateCode(int $userId): \Modules\TelegramBot\Models\LinkingCode
    {
        // Invalidate any unused codes for this user.
        \Modules\TelegramBot\Models\LinkingCode::query()
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->update(['used_at' => Carbon::now()]);

        return \Modules\TelegramBot\Models\LinkingCode::query()->create([
            'user_id' => $userId,
            'code' => strtoupper(Str::random(8)),
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);
    }
}
