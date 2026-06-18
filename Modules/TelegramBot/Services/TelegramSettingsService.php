<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\TelegramBot\Models\ConversationState;
use Modules\TelegramBot\Models\LearningProfile;
use Modules\TelegramBot\Models\Topic;
use Modules\TelegramBot\Models\UserPath;

/**
 * Lets an already-onboarded user change their learning settings from inside
 * the Telegram bot: purpose, level, daily send hour, and pause/resume.
 *
 * UX features:
 * - Confirmation prompt before resetting the user's roadmap on purpose change.
 * - Back / Cancel available at every step.
 * - Free-text fallback (e.g. user types "8" instead of tapping a time button).
 * - State machine uses `ConversationState` with current_command = 'settings_change'.
 *
 * This flow is intentionally independent from onboarding (separate
 * callback prefix `tgb:settings:` vs `tgb:onb:`) so the two flows cannot
 * step on each other if the user invokes both in quick succession.
 */
class TelegramSettingsService
{
    public const STATE_COMMAND = 'settings_change';

    /** Settings field → step key used in ConversationState. */
    private const FIELD_STEPS = [
        'purpose' => 'purpose',
        'level' => 'level',
        'hour' => 'hour',
    ];

    public function __construct(
        private readonly TelegramService $telegram,
        private readonly TelegramOnboardingService $onboarding,
    ) {
    }

    /**
     * Render the main settings screen with current values + change buttons.
     */
    public function sendSettings(string $chatId, User $user): void
    {
        $profile = LearningProfile::query()->where('user_id', $user->id)->first();

        if (! $profile) {
            $this->telegram->sendMessage(
                $chatId,
                "🔗 Bạn cần hoàn tất thiết lập trước. Gõ /start để bắt đầu."
            );
            return;
        }

        $pauseLabel = $profile->is_paused ? '▶️ Tiếp tục học' : '⏸ Tạm dừng học';

        $text = "⚙️ <b>CÀI ĐẶT HỌC TẬP</b>\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "🎯 Mục đích: <b>" . LearningProfile::purposes()[$profile->purpose] . "</b>\n"
            . "📊 Trình độ: <b>" . LearningProfile::levels()[$profile->level] . "</b>\n"
            . "⏰ Giờ nhận bài: <b>" . sprintf('%02d:00', $profile->daily_send_hour) . "</b>\n"
            . ($profile->is_paused ? "⏸ Trạng thái: <b>Đang tạm dừng</b>\n" : "▶️ Trạng thái: <b>Đang hoạt động</b>\n")
            . "\n━━━━━━━━━━━━━━━━━━━━\n\n"
            . "👇 <b>Bạn muốn thay đổi gì?</b>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎯 Đổi mục đích', 'callback_data' => 'tgb:settings:purpose'],
                    ['text' => '📊 Đổi trình độ', 'callback_data' => 'tgb:settings:level'],
                ],
                [
                    ['text' => '⏰ Đổi giờ nhận bài', 'callback_data' => 'tgb:settings:hour'],
                ],
                [
                    ['text' => $pauseLabel, 'callback_data' => 'tgb:settings:toggle'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Start a multi-step change flow for the given field.
     *
     * For 'toggle' this updates immediately. For purpose/level/hour, this
     * writes ConversationState and shows the corresponding prompt.
     */
    public function startChangeFlow(string $chatId, User $user, string $field): void
    {
        $profile = LearningProfile::query()->where('user_id', $user->id)->first();
        if (! $profile) {
            $this->telegram->sendMessage(
                $chatId,
                "🔗 Bạn cần hoàn tất thiết lập trước. Gõ /start để bắt đầu."
            );
            return;
        }

        if ($field === 'toggle') {
            $this->applyTogglePause($chatId, $user, $profile);
            return;
        }

        if ($field === 'cancel') {
            $this->handleCancel($chatId);
            return;
        }

        if (! isset(self::FIELD_STEPS[$field])) {
            // Unknown sub-action — fall back to the settings screen.
            $this->sendSettings($chatId, $user);
            return;
        }

        $state = ConversationState::forChat($chatId);
        $state->current_command = self::STATE_COMMAND;
        $state->state_data = [
            'step' => self::FIELD_STEPS[$field],
            'user_id' => $user->id,
            'field' => $field,
            'history' => [],
        ];
        $state->save();

        match ($field) {
            'purpose' => $this->sendPurposePrompt($chatId, []),
            'level' => $this->sendLevelPrompt($chatId, [], $user),
            'hour' => $this->sendHourPrompt($chatId, []),
        };
    }

    /**
     * Handle the user's choice on the purpose prompt.
     * Because changing purpose resets the roadmap, we always show a
     * confirmation dialog before persisting.
     */
    public function handlePurposeChoice(string $chatId, string $purpose, int $userId): void
    {
        if (! array_key_exists($purpose, LearningProfile::purposes())) {
            $this->telegram->sendMessage(
                $chatId,
                "🤔 Lựa chọn không hợp lệ. Vui lòng bấm một trong các nút bên dưới:"
            );
            $this->resendCurrentStep($chatId);
            return;
        }

        $state = ConversationState::forChat($chatId);
        $state->state_data = array_merge((array) $state->state_data, [
            'pending_purpose' => $purpose,
            'step' => 'purpose_confirm',
        ]);
        $state->save();

        $profile = LearningProfile::query()->where('user_id', $userId)->first();
        $currentLabel = $profile ? LearningProfile::purposes()[$profile->purpose] : '—';
        $newLabel = LearningProfile::purposes()[$purpose];

        $text = "⚠️ <b>Xác nhận đổi mục đích học</b>\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "Mục đích hiện tại: <b>{$currentLabel}</b>\n"
            . "Mục đích mới: <b>{$newLabel}</b>\n\n"
            . "📌 <b>Hệ quả:</b>\n"
            . "• Lộ trình hiện tại sẽ được <b>reset</b>\n"
            . "• Tiến độ các chủ đề đã học sẽ tính lại\n"
            . "• Bạn sẽ bắt đầu lại từ chủ đề đầu tiên của mục đích mới\n\n"
            . "Bạn có chắc chắn muốn đổi?";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Có, đổi lộ trình', 'callback_data' => "tgb:settings:purpose:confirm:{$purpose}"],
                    ['text' => '❌ Hủy', 'callback_data' => 'tgb:settings:cancel'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Persist the pending purpose change + reset the user's roadmap.
     */
    public function handlePurposeConfirm(string $chatId, int $userId): void
    {
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $purpose = $data['pending_purpose'] ?? null;

        if (! $purpose || ! array_key_exists($purpose, LearningProfile::purposes())) {
            $state->clear();
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ Phiên đổi cài đặt đã hết hạn. Vui lòng thử lại từ /settings."
            );
            return;
        }

        $profile = LearningProfile::query()->where('user_id', $userId)->first();
        if (! $profile) {
            $state->clear();
            return;
        }

        $oldPurpose = $profile->purpose;

        DB::transaction(function () use ($userId, $profile, $purpose) {
            $profile->purpose = $purpose;
            $profile->save();

            // Reset roadmap: drop all existing UserPath rows and reseed
            // using the new purpose's topic sequence. Old completed/skipped
            // progress is wiped.
            UserPath::query()->where('user_id', $userId)->delete();
            $this->reseedUserPath($userId, $profile);
        });

        $state->clear();

        $this->sendChangeSuccess(
            $chatId,
            '🎯 Mục đích học',
            LearningProfile::purposes()[$oldPurpose] . ' → ' . LearningProfile::purposes()[$purpose],
            'Đã reset lộ trình và bắt đầu từ chủ đề đầu tiên.'
        );
    }

    /**
     * Handle the user's choice on the level prompt. Persists immediately
     * (no confirmation — changing level doesn't reset roadmap).
     */
    public function handleLevelChoice(string $chatId, string $level, int $userId): void
    {
        if (! array_key_exists($level, LearningProfile::levels())) {
            $this->telegram->sendMessage(
                $chatId,
                "🤔 Lựa chọn không hợp lệ. Vui lòng bấm một trong các nút bên dưới:"
            );
            $this->resendCurrentStep($chatId);
            return;
        }

        $profile = LearningProfile::query()->where('user_id', $userId)->first();
        if (! $profile) {
            ConversationState::forChat($chatId)->clear();
            return;
        }

        $oldLevel = $profile->level;
        $profile->level = $level;
        $profile->save();

        ConversationState::forChat($chatId)->clear();

        $this->sendChangeSuccess(
            $chatId,
            '📊 Trình độ',
            LearningProfile::levels()[$oldLevel] . ' → ' . LearningProfile::levels()[$level],
            null
        );
    }

    /**
     * Handle a numeric hour choice (from one of the preset buttons).
     */
    public function handleHourChoice(string $chatId, int $hour, int $userId): void
    {
        if ($hour < 0 || $hour > 23) {
            $this->telegram->sendMessage(
                $chatId,
                "🤔 Giờ không hợp lệ. Vui lòng chọn từ 0 đến 23."
            );
            $this->sendHourPrompt($chatId, []);
            return;
        }

        $this->persistHour($chatId, $userId, $hour);
    }

    /**
     * Tell the user to type their custom hour value. The actual value is
     * matched later via handleFreeText().
     */
    public function handleHourCustom(string $chatId): void
    {
        $this->telegram->sendMessage(
            $chatId,
            "✏️ Gửi giờ bạn muốn nhận bài (0-23), ví dụ: <code>8</code> hoặc <code>20</code>"
        );
    }

    public function handleBack(string $chatId): void
    {
        // Single-step fields — "back" always re-sends the current prompt.
        $this->resendCurrentStep($chatId);
    }

    public function handleCancel(string $chatId): void
    {
        ConversationState::forChat($chatId)->clear();

        $this->telegram->sendMessage(
            $chatId,
            "❌ <b>Đã hủy thay đổi cài đặt.</b>\n\n"
            . "Cài đặt hiện tại được giữ nguyên.",
            [
                'inline_keyboard' => [
                    [
                        ['text' => '⚙️ Về cài đặt', 'callback_data' => 'tgb:settings'],
                        ['text' => '🏠 Menu chính', 'callback_data' => 'tgb:menu'],
                    ],
                ],
            ]
        );
    }

    /**
     * Route free-text input to the active settings-change step.
     */
    public function handleFreeText(string $chatId, string $text): void
    {
        $state = ConversationState::forChat($chatId);
        if ($state->current_command !== self::STATE_COMMAND) {
            return;
        }

        $data = (array) $state->state_data;
        $userId = (int) ($data['user_id'] ?? 0);
        $step = $data['step'] ?? '';

        if ($step === 'hour') {
            $hour = $this->matchHour($text);
            if ($hour === null) {
                $this->telegram->sendMessage(
                    $chatId,
                    "🤔 Mình chưa hiểu. Gửi giờ từ 0 đến 23 (ví dụ: <code>8</code>)."
                );
                $this->sendHourPrompt($chatId, (array) ($data['history'] ?? []));
                return;
            }
            $this->persistHour($chatId, $userId, $hour);
            return;
        }

        // For purpose/level — re-send the prompt.
        $this->telegram->sendMessage(
            $chatId,
            "🤔 Vui lòng bấm một trong các nút bên dưới:"
        );
        $this->resendCurrentStep($chatId);
    }

    // ---------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------

    private function applyTogglePause(string $chatId, User $user, LearningProfile $profile): void
    {
        $profile->is_paused = ! $profile->is_paused;
        $profile->save();

        $statusLine = $profile->is_paused
            ? '⏸ Đã <b>tạm dừng</b> — bạn sẽ không nhận bài tự động cho đến khi bật lại.'
            : '▶️ Đã <b>tiếp tục</b> — bạn sẽ nhận bài theo giờ đã thiết lập.';

        $this->telegram->sendMessage(
            $chatId,
            "✅ <b>Cập nhật trạng thái!</b>\n\n{$statusLine}",
            [
                'inline_keyboard' => [
                    [
                        ['text' => '⚙️ Về cài đặt', 'callback_data' => 'tgb:settings'],
                        ['text' => '🏠 Menu chính', 'callback_data' => 'tgb:menu'],
                    ],
                ],
            ]
        );
    }

    private function persistHour(string $chatId, int $userId, int $hour): void
    {
        $profile = LearningProfile::query()->where('user_id', $userId)->first();
        if (! $profile) {
            ConversationState::forChat($chatId)->clear();
            return;
        }

        $oldHour = $profile->daily_send_hour;
        $profile->daily_send_hour = $hour;
        $profile->save();

        ConversationState::forChat($chatId)->clear();

        $this->sendChangeSuccess(
            $chatId,
            '⏰ Giờ nhận bài',
            sprintf('%02d:00', $oldHour) . ' → ' . sprintf('%02d:00', $hour),
            null
        );
    }

    private function sendChangeSuccess(
        string $chatId,
        string $fieldLabel,
        string $changeSummary,
        ?string $extraNote
    ): void {
        $text = "✅ <b>Đã cập nhật cài đặt!</b>\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "{$fieldLabel}: <b>{$changeSummary}</b>";

        if ($extraNote) {
            $text .= "\n\n💡 <i>{$extraNote}</i>";
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '⚙️ Về cài đặt', 'callback_data' => 'tgb:settings'],
                    ['text' => '🏠 Menu chính', 'callback_data' => 'tgb:menu'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function resendCurrentStep(string $chatId): void
    {
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $userId = $data['user_id'] ?? null;
        $user = $userId ? User::find($userId) : null;
        $step = $data['step'] ?? '';
        $history = (array) ($data['history'] ?? []);

        match ($step) {
            'purpose' => $this->sendPurposePrompt($chatId, $history),
            'level' => $this->sendLevelPrompt($chatId, $history, $user),
            'hour' => $this->sendHourPrompt($chatId, $history),
            default => null,
        };
    }

    private function sendPurposePrompt(string $chatId, array $history): void
    {
        $header = $this->settingsHeader('🎯 Mục đích học', $history, 'Đang thay đổi');
        $text = $header
            . "🎯 <b>Bạn muốn đổi sang mục đích nào?</b>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎓 IELTS (Academic)', 'callback_data' => 'tgb:settings:purpose:ielts'],
                    ['text' => '💬 Giao tiếp hằng ngày', 'callback_data' => 'tgb:settings:purpose:daily'],
                ],
                [
                    ['text' => '💼 Công việc / Business', 'callback_data' => 'tgb:settings:purpose:business'],
                ],
                [
                    ['text' => '❌ Hủy', 'callback_data' => 'tgb:settings:cancel'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function sendLevelPrompt(string $chatId, array $history, ?User $user): void
    {
        $suggestion = '';
        if ($user?->target_band) {
            $suggested = (new LearningProfile())->suggestLevel($user->target_band);
            $suggestion = "\n💡 <i>Target band {$user->target_band} của bạn gợi ý: <b>"
                . LearningProfile::levels()[$suggested] . '</b></i>';
        }

        $header = $this->settingsHeader('📊 Trình độ', $history, 'Đang thay đổi');
        $text = $header
            . "📊 <b>Bạn muốn đổi sang trình độ nào?</b>"
            . $suggestion;

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌱 Cơ bản (A1-A2)', 'callback_data' => 'tgb:settings:level:beginner'],
                    ['text' => '📗 Trung cấp (B1-B2)', 'callback_data' => 'tgb:settings:level:intermediate'],
                ],
                [
                    ['text' => '🚀 Nâng cao (C1+)', 'callback_data' => 'tgb:settings:level:advanced'],
                ],
                [
                    ['text' => '❌ Hủy', 'callback_data' => 'tgb:settings:cancel'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function sendHourPrompt(string $chatId, array $history): void
    {
        $header = $this->settingsHeader('⏰ Giờ nhận bài', $history, 'Đang thay đổi');
        $text = $header
            . "⏰ <b>Bạn muốn nhận bài lúc mấy giờ?</b>\n\n"
            . "<i>Múi giờ: Asia/Ho_Chi_Minh</i>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌅 7:00', 'callback_data' => 'tgb:settings:hour:7'],
                    ['text' => '☀️ 12:00', 'callback_data' => 'tgb:settings:hour:12'],
                ],
                [
                    ['text' => '🌙 19:00', 'callback_data' => 'tgb:settings:hour:19'],
                    ['text' => '🌃 21:00', 'callback_data' => 'tgb:settings:hour:21'],
                ],
                [
                    ['text' => '✏️ Giờ khác', 'callback_data' => 'tgb:settings:hour:custom'],
                ],
                [
                    ['text' => '❌ Hủy', 'callback_data' => 'tgb:settings:cancel'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Build the header used at the top of every settings-change prompt.
     */
    private function settingsHeader(string $title, array $history, string $badge): string
    {
        $lines = [];
        $lines[] = "⚙️ <b>{$title}</b> <i>({$badge})</i>";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
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
        $lines[] = "";
        return implode("\n", $lines);
    }

    private function matchHour(string $text): ?int
    {
        if (preg_match('/\b(\d{1,2})\b/', $text, $m)) {
            $h = (int) $m[1];
            if ($h >= 0 && $h <= 23) {
                return $h;
            }
        }
        return null;
    }

    /**
     * Reset + reseed UserPath for the given purpose. Delegates to the
     * onboarding service so the seeding logic lives in one place.
     */
    private function reseedUserPath(int $userId, LearningProfile $profile): void
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
                    'completed_at' => null,
                    'word_count_target' => 5,
                ]
            );
        }
    }
}