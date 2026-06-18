<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\TelegramBot\Models\ConversationState;
use Modules\TelegramBot\Models\LearningProfile;
use Modules\TelegramBot\Models\LinkingCode;
use Modules\TelegramBot\Models\Topic;
use Modules\TelegramBot\Models\UserPath;
use Modules\TelegramBot\Models\UserTelegramLink;

/**
 * Drives the /start onboarding wizard via inline keyboards.
 *
 * UX features:
 * - Visual progress bar (🟩🟩⬜⬜ ...) at the top of every step.
 * - Back button (◀️ Quay lại) on every step after the first.
 * - Cancel button (❌ Hủy) that wipes the conversation state.
 * - Each step echoes the user's previous selections so they can sanity-check.
 */
class TelegramOnboardingService
{
    public const STEPS = ['purpose', 'level', 'hour'];

    public const STEP_LABELS = [
        'purpose' => 'Mục đích học',
        'level' => 'Trình độ',
        'hour' => 'Giờ nhận bài',
    ];

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

        $link = LinkingCode::query()->where('code', $code)->first();

        if (! $link || ! $link->isUsable()) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ <b>Mã liên kết không hợp lệ hoặc đã hết hạn.</b>\n\n"
                . "Vui lòng tạo mã mới tại:\n"
                . "🌐 " . url('/student/settings/telegram')
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

        $this->telegram->sendMessage(
            $chatId,
            "✅ <b>Liên kết thành công!</b>\n"
            . "Tài khoản <b>{$link->user->name}</b> đã được kết nối với Telegram.\n\n"
            . "Bây giờ hãy cùng thiết lập lộ trình học nhé 👇"
        );

        $this->askPurpose($chatId, $link->user);
    }

    public function showLinkInstructions(string $chatId): void
    {
        $text = "👋 <b>Chào mừng đến với EnglishClass Bot!</b>\n\n"
            . "🤖 <b>Bot này giúp gì?</b>\n"
            . "📚 Gửi từ vựng mới mỗi ngày theo chủ đề\n"
            . "🧠 Cấu trúc câu hay kèm ví dụ\n"
            . "🔁 Ôn tập thông minh (Spaced Repetition)\n"
            . "📝 Quiz & mini-game để luyện tập\n\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "🔗 <b>Cách liên kết (3 bước):</b>\n\n"
            . "<b>1️⃣</b> Đăng nhập trang web\n"
            . "<b>2️⃣</b> Vào <b>Cài đặt → 🤖 Telegram Bot</b>\n"
            . "<b>3️⃣</b> Tạo mã và gửi cho bot theo mẫu:\n\n"
            . "👉 <code>/start MA_CUA_BAN</code>";

        $reply = [
            'inline_keyboard' => [
                [
                    ['text' => '🌐 Mở trang cài đặt', 'url' => url('/student/settings/telegram')],
                ],
                [
                    ['text' => '🎓 Xem hướng dẫn sử dụng', 'callback_data' => 'tgb:help'],
                ],
            ],
        ];
        $this->telegram->sendMessage($chatId, $text, $reply);
    }

    public function askPurpose(string $chatId, User $user): void
    {
        $state = ConversationState::forChat($chatId);
        $state->current_command = 'onboarding';
        $state->state_data = [
            'step' => 'purpose',
            'user_id' => $user->id,
            'history' => [],
        ];
        $state->save();

        $this->sendPurposePrompt($chatId, []);
    }

    public function handlePurpose(string $chatId, string $purpose, int $userId): void
    {
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $data['purpose'] = $purpose;
        $data['step'] = 'level';
        $data['history'] = array_merge((array) ($data['history'] ?? []), ['purpose' => $purpose]);
        $state->state_data = $data;
        $state->save();

        $user = User::find($userId);
        $this->sendLevelPrompt($chatId, $data['history'], $user);
    }

    public function handleLevel(string $chatId, string $level, int $userId): void
    {
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $data['level'] = $level;
        $data['step'] = 'hour';
        $data['history'] = array_merge((array) ($data['history'] ?? []), ['level' => $level]);
        $state->state_data = $data;
        $state->save();

        $this->sendHourPrompt($chatId, $data['history']);
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

        $summary = "🎉 <b>Hoàn tất thiết lập!</b>\n\n"
            . $this->progressBar(3, 3) . "\n\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "✅ <b>Lộ trình của bạn:</b>\n"
            . "🎯 Mục đích: <b>" . LearningProfile::purposes()[$profile->purpose] . "</b>\n"
            . "📊 Trình độ: <b>" . LearningProfile::levels()[$profile->level] . "</b>\n"
            . "⏰ Giờ nhận bài: <b>" . sprintf('%02d:00', $hour) . "</b>\n\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "📅 Bài học đầu tiên sẽ tới vào <b>" . sprintf('%02d:00', $hour) . "</b> ngày mai.\n\n"
            . "📚 <b>Các lệnh hữu ích:</b>\n"
            . "/vocab - Từ vựng hôm nay\n"
            . "/grammar - Cấu trúc câu\n"
            . "/quiz - Làm bài quiz\n"
            . "/review - Ôn tập từ cũ\n"
            . "/roadmap - Xem lộ trình\n"
            . "/settings - Đổi cài đặt\n"
            . "/extra - Học thêm bài (nếu được cấp quyền)\n\n"
            . "Gõ /help để xem tất cả lệnh.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📚 Xem lộ trình', 'callback_data' => 'tgb:roadmap'],
                    ['text' => '🎓 Bài học đầu tiên', 'callback_data' => 'tgb:lesson:first'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $summary, $keyboard);
    }

    /**
     * Handle a "Back" press from any step.
     */
    public function handleBack(string $chatId): void
    {
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $userId = $data['user_id'] ?? null;
        $history = (array) ($data['history'] ?? []);

        $currentStep = $data['step'] ?? 'purpose';
        $currentIdx = array_search($currentStep, self::STEPS, true);

        if ($currentIdx === false || $currentIdx === 0) {
            // Already on first step - just resend.
            $this->resendCurrentStep($chatId, $data);
            return;
        }

        $prevStep = self::STEPS[$currentIdx - 1];
        $data['step'] = $prevStep;
        // Remove the answer for the step we're going back from.
        if ($prevStep === 'purpose') {
            unset($data['purpose']);
        } elseif ($prevStep === 'level') {
            unset($data['level']);
        }
        // history is a record of all answers ever given; we keep it for re-display.
        $state->state_data = $data;
        $state->save();

        $this->resendCurrentStep($chatId, $data);
    }

    /**
     * Handle a "Cancel" press - clear state, send a friendly goodbye.
     */
    public function handleCancel(string $chatId): void
    {
        ConversationState::forChat($chatId)->clear();

        $this->telegram->sendMessage(
            $chatId,
            "❌ <b>Đã hủy thiết lập.</b>\n\n"
            . "Bạn có thể bắt đầu lại bất cứ lúc nào bằng /start.\n"
            . "Gõ /help để xem các lệnh."
        );
    }

    /**
     * Called when the user sends free text while a wizard step is active.
     * Best-effort match against the visible options.
     */
    public function handleFreeText(string $chatId, string $text): void
    {
        $state = ConversationState::forChat($chatId);
        if ($state->current_command !== 'onboarding') {
            return;
        }

        $data = (array) $state->state_data;
        $step = $data['step'] ?? 'purpose';
        $text = strtolower(trim($text));

        $match = match ($step) {
            'purpose' => $this->matchPurpose($text),
            'level' => $this->matchLevel($text),
            'hour' => $this->matchHour($text),
            default => null,
        };

        if ($match) {
            if ($step === 'purpose') {
                $this->handlePurpose($chatId, $match, (int) $data['user_id']);
            } elseif ($step === 'level') {
                $this->handleLevel($chatId, $match, (int) $data['user_id']);
            } elseif ($step === 'hour') {
                $this->handleHour($chatId, (int) $match, (int) $data['user_id']);
            }
            return;
        }

        // No match - re-send current step with a hint.
        $this->telegram->sendMessage(
            $chatId,
            "🤔 Mình chưa hiểu lựa chọn của bạn. Vui lòng bấm một trong các nút bên dưới:"
        );
        $this->resendCurrentStep($chatId, $data);
    }

    private function matchPurpose(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'ielts') => 'ielts',
            str_contains($text, 'giao') || str_contains($text, 'daily') || str_contains($text, 'nói') || str_contains($text, 'chuyện') => 'daily',
            str_contains($text, 'công') || str_contains($text, 'việc') || str_contains($text, 'business') || str_contains($text, 'job') => 'business',
            default => null,
        };
    }

    private function matchLevel(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'cơ bản') || str_contains($text, 'beginner') || str_contains($text, 'a1') || str_contains($text, 'a2') => 'beginner',
            str_contains($text, 'nâng cao') || str_contains($text, 'advanced') || str_contains($text, 'c1') || str_contains($text, 'c2') => 'advanced',
            str_contains($text, 'trung') || str_contains($text, 'intermediate') || str_contains($text, 'b1') || str_contains($text, 'b2') => 'intermediate',
            default => null,
        };
    }

    private function matchHour(string $text): ?int
    {
        // Try to extract a number 0-23 from the text.
        if (preg_match('/\b(\d{1,2})\b/', $text, $m)) {
            $h = (int) $m[1];
            if ($h >= 0 && $h <= 23) {
                return $h;
            }
        }
        return null;
    }

    private function resendCurrentStep(string $chatId, array $data): void
    {
        $userId = $data['user_id'] ?? null;
        $history = (array) ($data['history'] ?? []);
        $user = $userId ? User::find($userId) : null;
        $step = $data['step'] ?? 'purpose';

        match ($step) {
            'purpose' => $this->sendPurposePrompt($chatId, $history, $user),
            'level' => $this->sendLevelPrompt($chatId, $history, $user),
            'hour' => $this->sendHourPrompt($chatId, $history),
            default => null,
        };
    }

    private function sendPurposePrompt(string $chatId, array $history, ?User $user = null): void
    {
        $text = $this->wizardHeader('purpose', $history, 1)
            . "🎯 <b>Mục đích học của bạn là gì?</b>\n\n"
            . "<i>Mục đích này sẽ quyết định chủ đề từ vựng mỗi ngày.</i>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎓 IELTS (Academic)', 'callback_data' => 'tgb:onb:purpose:ielts'],
                    ['text' => '💬 Giao tiếp hằng ngày', 'callback_data' => 'tgb:onb:purpose:daily'],
                ],
                [
                    ['text' => '💼 Công việc / Business', 'callback_data' => 'tgb:onb:purpose:business'],
                ],
                [
                    ['text' => '❌ Hủy', 'callback_data' => 'tgb:onb:cancel'],
                ],
            ],
        ];
        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function sendLevelPrompt(string $chatId, array $history, ?User $user = null): void
    {
        $suggestion = '';
        if ($user?->target_band) {
            $suggested = (new LearningProfile())->suggestLevel($user->target_band);
            $suggestion = "\n💡 <i>Target band {$user->target_band} của bạn gợi ý: <b>"
                . LearningProfile::levels()[$suggested] . '</b></i>';
        }

        $text = $this->wizardHeader('level', $history, 2)
            . "📊 <b>Trình độ hiện tại của bạn?</b>"
            . $suggestion;

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌱 Cơ bản (A1-A2)', 'callback_data' => 'tgb:onb:level:beginner'],
                    ['text' => '📗 Trung cấp (B1-B2)', 'callback_data' => 'tgb:onb:level:intermediate'],
                ],
                [
                    ['text' => '🚀 Nâng cao (C1+)', 'callback_data' => 'tgb:onb:level:advanced'],
                ],
                [
                    ['text' => '◀️ Quay lại', 'callback_data' => 'tgb:onb:back'],
                    ['text' => '❌ Hủy', 'callback_data' => 'tgb:onb:cancel'],
                ],
            ],
        ];
        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function sendHourPrompt(string $chatId, array $history): void
    {
        $text = $this->wizardHeader('hour', $history, 3)
            . "⏰ <b>Bạn muốn nhận bài học lúc mấy giờ?</b>\n\n"
            . "<i>Múi giờ: Asia/Ho_Chi_Minh. Bạn có thể đổi sau trong /settings.</i>";

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
                [
                    ['text' => '✏️ Giờ khác', 'callback_data' => 'tgb:onb:hour:custom'],
                ],
                [
                    ['text' => '◀️ Quay lại', 'callback_data' => 'tgb:onb:back'],
                    ['text' => '❌ Hủy', 'callback_data' => 'tgb:onb:cancel'],
                ],
            ],
        ];
        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Build the header used by every wizard step.
     */
    private function wizardHeader(string $currentStep, array $history, int $stepNumber): string
    {
        $idx = array_search($currentStep, self::STEPS, true);
        $bar = $this->progressBar($idx + 1, count(self::STEPS));

        $lines = [];
        $lines[] = $bar;
        $lines[] = "📝 <b>Thiết lập lộ trình - Bước {$stepNumber}/3</b>";

        if (! empty($history)) {
            $lines[] = "";
            $lines[] = "<i>Đã chọn:</i>";
            if (isset($history['purpose'])) {
                $lines[] = "  🎯 " . LearningProfile::purposes()[$history['purpose']];
            }
            if (isset($history['level'])) {
                $lines[] = "  📊 " . LearningProfile::levels()[$history['level']];
            }
            if (isset($history['hour'])) {
                $lines[] = "  ⏰ {$history['hour']}:00";
            }
        }

        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        return implode("\n", $lines) . "\n\n";
    }

    /**
     * Build a 4-block progress bar.
     */
    private function progressBar(int $current, int $total): string
    {
        // Use squares at half-step granularity. E.g. 1/3 -> 🟩⬜⬜
        $filled = $current;
        $empty = max(0, $total - $current);
        return str_repeat('🟩', $filled) . str_repeat('⬜', $empty);
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
    public static function generateCode(int $userId): LinkingCode
    {
        // Invalidate any unused codes for this user.
        LinkingCode::query()
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->update(['used_at' => Carbon::now()]);

        return LinkingCode::query()->create([
            'user_id' => $userId,
            'code' => strtoupper(Str::random(8)),
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);
    }
}
