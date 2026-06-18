<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Models\ConversationState;
use Modules\TelegramBot\Models\ReviewSchedule;
use Modules\TelegramBot\Models\UserPath;
use Modules\TelegramBot\Models\UserTelegramLink;
use Modules\TelegramBot\Models\VocabularyEntry;

/**
 * Dispatches incoming Telegram commands and callback queries.
 */
class TelegramBotCommandService
{
    public function __construct(
        private readonly TelegramService $telegram,
        private readonly TelegramOnboardingService $onboarding,
        private readonly TelegramQuizService $quiz,
        private readonly SpacedRepetitionService $sr,
        private readonly TelegramLearningService $learning,
        private readonly TelegramGameService $game,
        private readonly TelegramSettingsService $settings,
        private readonly AchievementService $achievements,
        private readonly LevelService $levels,
        private readonly TextToSpeechService $tts,
    ) {
    }

    /**
     * Handle a /command message.
     */
    public function handleCommand(string $chatId, string $command, string $args, ?string $username = null, ?User $user = null): void
    {
        switch ($command) {
            case 'start':
                $this->onboarding->startWizard($chatId, $args ?: null, $username);
                break;

            case 'help':
                $this->sendHelp($chatId);
                break;

            case 'vocab':
                if (! $user) { $this->requireLink($chatId); return; }
                $this->sendRecentVocab($chatId, $user);
                break;

            case 'grammar':
                if (! $user) { $this->requireLink($chatId); return; }
                $this->sendRecentGrammar($chatId, $user);
                break;

            case 'quiz':
                if (! $user) { $this->requireLink($chatId); return; }
                $this->quiz->startQuiz($chatId, $user);
                break;

            case 'review':
                if (! $user) { $this->requireLink($chatId); return; }
                $this->startReviewSession($chatId, $user);
                break;

            case 'roadmap':
                if (! $user) { $this->requireLink($chatId); return; }
                $this->sendRoadmap($chatId, $user);
                break;

            case 'settings':
                if (! $user) { $this->requireLink($chatId); return; }
                $this->sendSettings($chatId, $user);
                break;

            case 'game':
                if (! $user) { $this->requireLink($chatId); return; }
                $this->game->showMenu($chatId);
                break;

            case 'extra':
                if (! $user) { $this->requireLink($chatId); return; }
                $this->handleExtraLesson($chatId, $user);
                break;

            case 'achievements':
                if (! $user) { $this->requireLink($chatId); return; }
                $this->sendAchievementsList($chatId, $user);
                break;

            case 'menu':
                if (! $user) { $this->requireLink($chatId); return; }
                $this->sendMainMenu($chatId, $user);
                break;

            default:
                $this->telegram->sendMessage(
                    $chatId,
                    "🤔 Lệnh /{$command} chưa được hỗ trợ. Gõ /help để xem danh sách."
                );
        }
    }

    /**
     * Resolve the user linked to this chat id, if any.
     */
    public function resolveUser(string $chatId): ?User
    {
        $link = UserTelegramLink::query()->where('telegram_chat_id', $chatId)->first();
        if (! $link) {
            return null;
        }

        $link->last_interaction_at = Carbon::now();
        $link->save();

        return $link->user;
    }

    /**
     * Route free text from the user. Used for onboarding fallback
     * (e.g. user types "8" instead of tapping a time button).
     */
    public function handleFreeText(string $chatId, string $text, ?User $user = null): void
    {
        if (! $user) {
            $this->telegram->sendMessage(
                $chatId,
                "🔗 Bạn cần liên kết tài khoản trước. Gõ /start để bắt đầu."
            );
            return;
        }

        // Touch last_interaction_at first so the welcome-back check below
        // sees fresh data (otherwise we'd re-greet on every message).
        UserTelegramLink::query()
            ->where('user_id', $user->id)
            ->update(['last_interaction_at' => Carbon::now()]);

        // Welcome-back nudge: if the user's last interaction was > 36h
        // ago, send an empathetic recap + invite them back BEFORE we
        // route the current text to whatever handler below.
        $link = UserTelegramLink::query()->where('user_id', $user->id)->first();
        $this->maybeSendWelcomeBack($chatId, $user, $link);

        $state = ConversationState::query()->where('telegram_chat_id', $chatId)->first();

        if ($state && $state->current_command === 'onboarding') {
            $this->onboarding->handleFreeText($chatId, $text);
            return;
        }

        if ($state && $state->current_command === 'settings_change') {
            $this->settings->handleFreeText($chatId, $text);
            return;
        }

        if ($state && $state->current_command === 'game') {
            $this->game->handleAnswer($chatId, $user, $text);
            return;
        }

        // No active flow - send a friendly reminder of available commands.
        $this->telegram->sendMessage(
            $chatId,
            "💬 Mình nhận được: <i>\"" . mb_substr($text, 0, 50) . '"</i>\n\n'
            . "Gõ /help để xem danh sách lệnh.\n"
            . "Gõ /start để bắt đầu nếu bạn chưa liên kết tài khoản."
        );
    }

    /**
     * If the user has been idle for > 36h, send a single empathetic
     * welcome-back message. Skipped when:
     *   - the user is mid-flow (ConversationState set),
     *   - the user just sent a /command (commands are handled by
     *     handleCommand, which calls resolveUser but NOT this path),
     *   - we've already sent a welcome-back within the last 12h
     *     (cached in `tgb:welcome_back:{user_id}`).
     */
    private function maybeSendWelcomeBack(string $chatId, User $user, ?UserTelegramLink $link): void
    {
        if (! $link || ! $link->last_interaction_at) {
            return;
        }

        $state = ConversationState::query()->where('telegram_chat_id', $chatId)->first();
        if ($state && $state->current_command !== null) {
            return; // don't interrupt an in-progress flow
        }

        $idleSince = $link->last_interaction_at;
        $hoursIdle = (int) $idleSince->diffInHours(Carbon::now());
        if ($hoursIdle < 36) {
            return;
        }

        // Throttle: only fire once per 12h even if the user keeps messaging.
        $throttleKey = "tgb:welcome_back:{$user->id}";
        if (cache()->has($throttleKey)) {
            return;
        }

        $days = max(1, (int) Carbon::now()->diffInDays($idleSince));
        $streakWarning = $user->streak > 0
            ? "\n⚠️ Streak của bạn đã đứt từ <b>{$user->streak} ngày</b> rồi — nhưng không sao, hãy bắt đầu lại nhé!"
            : "";

        $this->telegram->sendMessage(
            $chatId,
            "👋 <b>Chào mừng quay lại!</b>\n\n"
            . "Đã <b>{$days} ngày</b> kể từ lần cuối bạn ghé bot.{$streakWarning}\n\n"
            . "🌱 Mỗi ngày nhỏ đều đếm — bắt đầu lại từ hôm nay thôi!",
            [
                'inline_keyboard' => [
                    [
                        ['text' => '📚 Học tiếp ngay', 'callback_data' => 'tgb:q:start'],
                    ],
                    [
                        ['text' => '📍 Xem lộ trình', 'callback_data' => 'tgb:roadmap'],
                        ['text' => '🏠 Menu chính', 'callback_data' => 'tgb:menu'],
                    ],
                ],
            ]
        );

        cache()->put($throttleKey, 1, now()->addHours(12));
    }

    public function handleCallback(string $chatId, string $callbackId, string $data, ?int $messageId, ?User $user = null): void
    {
        $this->telegram->answerCallbackQuery($callbackId);

        // Format: tgb:<action>:<arg1>:<arg2>...
        $parts = explode(':', $data);
        if (count($parts) < 2 || $parts[0] !== 'tgb') {
            return;
        }
        $action = $parts[1] ?? '';

        switch ($action) {
            case 'listen':
                $token = $parts[2] ?? '';
                $audio = $token !== '' ? $this->tts->audioForCallback($token) : null;

                if ($audio === null) {
                    $this->telegram->sendMessage(
                        $chatId,
                        "⚠️ Audio đã hết hạn hoặc tạm thời chưa tạo được. Vui lòng mở bài học mới và thử lại."
                    );
                    break;
                }

                $this->telegram->sendChatAction($chatId, 'upload_voice');
                if ($this->telegram->sendAudio(
                    $chatId,
                    $audio['audio'],
                    '🎧 ' . $audio['text']
                ) === null) {
                    $this->telegram->sendMessage(
                        $chatId,
                        "⚠️ Chưa gửi được audio vào Telegram. Vui lòng thử lại sau."
                    );
                }
                break;

            case 'help':
                $this->sendHelp($chatId);
                break;

            case 'onb':
                if (! $user) { $this->requireLink($chatId); return; }
                $step = $parts[2] ?? '';
                $value = $parts[3] ?? '';
                if ($step === 'back') {
                    $this->onboarding->handleBack($chatId);
                } elseif ($step === 'cancel') {
                    $this->onboarding->handleCancel($chatId);
                } elseif ($step === 'purpose') {
                    $this->onboarding->handlePurpose($chatId, $value, $user->id);
                } elseif ($step === 'level') {
                    $this->onboarding->handleLevel($chatId, $value, $user->id);
                } elseif ($step === 'hour') {
                    if ($value === 'custom') {
                        $this->telegram->sendMessage(
                            $chatId,
                            "✏️ Gửi giờ bạn muốn nhận bài (0-23), ví dụ: <code>8</code> hoặc <code>20</code>"
                        );
                    } else {
                        $this->onboarding->handleHour($chatId, (int) $value, $user->id);
                    }
                }
                break;

            case 'v': // view lesson detail
                if (! $user) return;
                $lessonId = (int) ($parts[2] ?? 0);
                $this->sendLessonDetail($chatId, $messageId, $user, $lessonId);
                break;

            case 'q': // start quiz for a lesson
                if (! $user) return;
                $this->quiz->startQuiz($chatId, $user);
                break;

            case 'roadmap':
                if (! $user) return;
                $this->sendRoadmap($chatId, $user);
                break;

            case 'settings':
                if (! $user) return;
                $sub = $parts[2] ?? '';
                $value = $parts[3] ?? '';

                if ($sub === '') {
                    // tgb:settings → show main settings screen
                    $this->settings->sendSettings($chatId, $user);
                } elseif ($sub === 'purpose' && $value !== '') {
                    // tgb:settings:purpose:<value> — user picked a new purpose
                    $this->settings->handlePurposeChoice($chatId, $value, $user->id);
                } elseif ($sub === 'level' && $value !== '') {
                    // tgb:settings:level:<value> — pick a new level
                    $this->settings->handleLevelChoice($chatId, $value, $user->id);
                } elseif ($sub === 'hour' && $value === 'custom') {
                    // tgb:settings:hour:custom — ask for free-text hour
                    $this->settings->handleHourCustom($chatId);
                } elseif ($sub === 'hour' && $value !== '') {
                    // tgb:settings:hour:<h> — preset hour
                    $this->settings->handleHourChoice($chatId, (int) $value, $user->id);
                } elseif ($sub === 'cancel') {
                    // tgb:settings:cancel — exit settings-change wizard
                    $this->settings->handleCancel($chatId);
                } else {
                    // tgb:settings:purpose | :level | :hour | :toggle
                    // — open the corresponding change prompt
                    $this->settings->startChangeFlow($chatId, $user, $sub);
                }
                break;

            case 'lesson':
                if (! $user) return;
                // tgb:lesson:first — request an on-demand first lesson
                // (triggered from the onboarding summary screen).
                $this->handleExtraLesson($chatId, $user);
                break;

            case 'skip-topic':
                if (! $user) return;
                $this->skipCurrentTopic($chatId, $user);
                break;

            case 'extra':
                if (! $user) return;
                $this->handleExtraLesson($chatId, $user);
                break;

            case 'achievements':
                if (! $user) return;
                $this->sendAchievementsList($chatId, $user);
                break;

            case 'r': // review grade: tgb:r:<schedule_id>:<grade>
                if (! $user) return;
                $scheduleId = (int) ($parts[2] ?? 0);
                $grade = (int) ($parts[3] ?? 2);
                $this->applyReviewGrade($chatId, $messageId, $user, $scheduleId, $grade);
                break;

            case 'qa': // quiz answer: tgb:qa:<index>:<chosen>
                if (! $user) return;
                $index = (int) ($parts[2] ?? 0);
                $chosen = (int) ($parts[3] ?? -1);
                $this->quiz->gradeAnswer($chatId, $index, $chosen, $user);
                break;

            case 'rv': // start review session
                if (! $user) return;
                $this->startReviewSession($chatId, $user);
                break;

            case 'rskip': // skip current review card
                if (! $user) return;
                $this->skipReviewCard($chatId, $user);
                break;

            case 'menu': // main menu (single button from any flow)
                if (! $user) return;
                $this->sendMainMenu($chatId, $user);
                break;

            case 'vocab-detail':
                if (! $user) return;
                $this->sendRecentVocab($chatId, $user);
                break;

            case 'game':
                if (! $user) return;
                $gameType = $parts[2] ?? 'random';
                $this->game->startGame($chatId, $user, $gameType);
                break;

            case 'gmatch': // match pair answer: tgb:gmatch:<index>:<chosen>
                if (! $user) return;
                $idx = (int) ($parts[2] ?? 0);
                $chosen = (int) ($parts[3] ?? -1);
                $this->game->handleCallback($chatId, $user, $idx, $chosen);
                break;

            case 'gskip':
                if (! $user) return;
                $this->game->handleAnswer($chatId, $user, '__skip__');
                break;

            case 'gexit':
                if (! $user) return;
                \Modules\TelegramBot\Models\ConversationState::forChat($chatId)->clear();
                $this->telegram->sendMessage(
                    $chatId,
                    "👋 Đã thoát game. Hẹn gặp lại!",
                    ['inline_keyboard' => [[['text' => '🏠 Menu', 'callback_data' => 'tgb:menu']]]]
                );
                break;

            case 'ghint':
                if (! $user) return;
                $entryId = (int) ($parts[2] ?? 0);
                $entry = VocabularyEntry::query()->find($entryId);
                if ($entry) {
                    $this->telegram->sendMessage(
                        $chatId,
                        "💡 Gợi ý: từ bắt đầu bằng chữ <b>" . strtoupper(mb_substr($entry->word, 0, 1)) . "</b>"
                    );
                }
                break;

            default:
                Log::info('[TelegramBot] Unknown callback action', ['data' => $data]);
        }
    }

    private function requireLink(string $chatId): void
    {
        $this->onboarding->showLinkInstructions($chatId);
    }

    private function sendHelp(string $chatId): void
    {
        $text = "📚 <b>DANH SÁCH LỆNH</b>\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "🔗 <b>Liên kết & cài đặt:</b>\n"
            . "/start [CODE] - Liên kết tài khoản\n"
            . "/settings - Đổi mục đích, trình độ, giờ nhận, tạm dừng\n"
            . "/menu - Menu chính (dễ dùng)\n\n"
            . "📖 <b>Học tập:</b>\n"
            . "/vocab - Từ vựng hôm nay\n"
            . "/grammar - Cấu trúc câu hôm nay\n"
            . "/quiz - Quiz 5 câu trắc nghiệm\n"
            . "/review - Ôn tập thẻ đến hạn\n"
            . "/roadmap - Xem lộ trình học\n"
            . "/extra - Học thêm bài (yêu cầu quyền)\n"
            . "/achievements - Xem huy hiệu & thành tựu\n\n"
            . "🎮 <b>Giải trí:</b>\n"
            . "/game - Mini-game (Word Scramble, Match Pairs...)\n\n"
            . "💡 <b>Mẹo:</b> Gõ /menu để mở menu trực quan với các nút bấm.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🏠 Menu chính', 'callback_data' => 'tgb:menu'],
                    ['text' => '🚀 Bắt đầu nhanh', 'callback_data' => 'tgb:q:start'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function sendRecentVocab(string $chatId, User $user): void
    {
        $words = VocabularyEntry::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        if ($words->isEmpty()) {
            $this->telegram->sendMessage($chatId, "📭 Bạn chưa có từ vựng nào. Hãy đợi bài học hôm nay nhé!");
            return;
        }

        $lines = ["📚 <b>Từ vựng gần đây:</b>"];
        foreach ($words as $w) {
            $ipa = $w->ipa ? " <code>{$w->ipa}</code>" : '';
            $lines[] = "• <b>{$w->word}</b>{$ipa} — {$w->meaning_vi}";
            if ($w->example_en) {
                $lines[] = "   <i>\"{$w->example_en}\"</i>";
            }
        }
        $lines[] = "";
        $lines[] = "Gõ /quiz để luyện tập!";

        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    private function sendRecentGrammar(string $chatId, User $user): void
    {
        $grammar = \Modules\TelegramBot\Models\GrammarEntry::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        if (! $grammar) {
            $this->telegram->sendMessage($chatId, "📭 Chưa có cấu trúc câu nào. Hãy đợi bài học hôm nay nhé!");
            return;
        }

        $lines = [];
        $lines[] = "🧠 <b>Cấu trúc câu:</b>";
        $lines[] = "<code>{$grammar->structure}</code>";
        if ($grammar->explanation_vi) {
            $lines[] = "💡 {$grammar->explanation_vi}";
        }
        if ($grammar->example_en) {
            $lines[] = "✏️ <i>{$grammar->example_en}</i>";
        }
        if ($grammar->example_vi) {
            $lines[] = "🇻🇳 {$grammar->example_vi}";
        }

        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    private function sendRoadmap(string $chatId, User $user): void
    {
        $paths = UserPath::query()
            ->with('topic')
            ->where('user_id', $user->id)
            ->join('tgb_topics', 'tgb_topics.id', '=', 'tgb_user_paths.topic_id')
            ->orderBy('tgb_topics.order_index')
            ->select('tgb_user_paths.*')
            ->get();

        if ($paths->isEmpty()) {
            $this->telegram->sendMessage(
                $chatId,
                "📭 <b>Bạn chưa có lộ trình.</b>\n\n"
                . "Gõ /start để hoàn tất thiết lập ban đầu nhé!"
            );
            return;
        }

        $profile = \Modules\TelegramBot\Models\LearningProfile::query()
            ->where('user_id', $user->id)
            ->first();

        $purposeLabel = $profile
            ? \Modules\TelegramBot\Models\LearningProfile::purposes()[$profile->purpose] ?? $profile->purpose
            : 'IELTS';
        $levelLabel = $profile
            ? \Modules\TelegramBot\Models\LearningProfile::levels()[$profile->level] ?? $profile->level
            : '';

        $total = $paths->count();
        $completed = $paths->where('status', UserPath::STATUS_COMPLETED)->count();
        $skipped = $paths->where('status', UserPath::STATUS_SKIPPED)->count();
        $pct = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
        $bar = $this->progressBar($pct, 100);

        // Build per-topic progress for current topic.
        $currentPath = $paths->firstWhere('status', UserPath::STATUS_CURRENT);
        $currentDetail = '';
        if ($currentPath) {
            $totalWords = \Modules\TelegramBot\Models\VocabularyEntry::query()
                ->where('user_id', $user->id)
                ->where('topic_id', $currentPath->topic_id)
                ->count();
            $matureWords = \Modules\TelegramBot\Models\ReviewSchedule::query()
                ->where('user_id', $user->id)
                ->whereHas('vocabularyEntry', function ($q) use ($user, $currentPath) {
                    $q->where('user_id', $user->id)->where('topic_id', $currentPath->topic_id);
                })
                ->where('repetitions', '>=', 2)
                ->count();
            $currentDetail = "📍 <b>Đang học:</b> {$currentPath->topic->name_vi}\n"
                . "   Từ vựng: <b>{$matureWords}/{$totalWords}</b> đã thuộc\n\n";
        }

        $lines = [];
        $lines[] = "📍 <b>LỘ TRÌNH HỌC TẬP</b>";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "";
        $lines[] = "🎯 Mục đích: <b>{$purposeLabel}</b>" . ($levelLabel ? " | 📊 {$levelLabel}" : '');
        $lines[] = "";
        $lines[] = "🏆 Tiến độ tổng: <b>{$pct}%</b> ({$completed}/{$total} chủ đề)";
        $lines[] = $bar;
        $lines[] = "";
        if ($currentDetail) {
            $lines[] = $currentDetail;
        }
        $lines[] = "<b>Danh sách chủ đề:</b>";

        $shown = $paths->take(10);
        foreach ($shown as $i => $p) {
            $icon = match ($p->status) {
                UserPath::STATUS_COMPLETED => '✅',
                UserPath::STATUS_CURRENT => '🔵',
                UserPath::STATUS_SKIPPED => '⏭️',
                default => '🔒',
            };
            $marker = $p->status === UserPath::STATUS_CURRENT ? ' ← đang học' : '';
            $lines[] = "{$icon} <b>" . ($i + 1) . ".</b> {$p->topic->name_vi}{$marker}";
        }

        if ($paths->count() > 10) {
            $lines[] = "<i>... và " . ($paths->count() - 10) . " chủ đề khác</i>";
        }

        $lines[] = "";
        $lines[] = "💡 Hoàn thành tất cả từ vựng + quiz trong chủ đề hiện tại để mở khóa chủ đề tiếp theo.";

        $keyboard = null;
        if ($currentPath) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📚 Học tiếp', 'callback_data' => 'tgb:q:start'],
                        ['text' => '⏭ Bỏ qua', 'callback_data' => 'tgb:skip-topic'],
                    ],
                ],
            ];
        }

        $this->telegram->sendMessage($chatId, implode("\n", $lines), $keyboard);
    }

    private function sendSettings(string $chatId, User $user): void
    {
        $profile = \Modules\TelegramBot\Models\LearningProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            $this->requireLink($chatId);
            return;
        }

        $text = "⚙️ <b>Cài đặt</b>\n\n"
            . "🎯 Mục đích: " . \Modules\TelegramBot\Models\LearningProfile::purposes()[$profile->purpose] . "\n"
            . "📊 Trình độ: " . \Modules\TelegramBot\Models\LearningProfile::levels()[$profile->level] . "\n"
            . "⏰ Giờ nhận bài: {$profile->daily_send_hour}:00\n"
            . ($profile->is_paused ? "⏸ Đang tạm dừng\n" : "▶️ Đang hoạt động\n");

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎯 Đổi mục đích', 'callback_data' => 'tgb:settings:purpose'],
                    ['text' => '📊 Đổi trình độ', 'callback_data' => 'tgb:settings:level'],
                ],
                [
                    ['text' => $profile->is_paused ? '▶️ Tiếp tục' : '⏸ Tạm dừng',
                     'callback_data' => 'tgb:settings:toggle'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function startReviewSession(string $chatId, User $user): void
    {
        $due = ReviewSchedule::query()
            ->with('vocabularyEntry')
            ->where('user_id', $user->id)
            ->due()
            ->orderBy('next_review_at')
            ->limit(10)
            ->get();

        if ($due->isEmpty()) {
            $this->telegram->sendMessage(
                $chatId,
                "🎉 <b>Không có thẻ nào cần ôn.</b>\n\n"
                . "Bạn đang theo kịp lộ trình. Hãy quay lại khi có thẻ mới nhé!\n\n"
                . "💡 Gõ /vocab để học từ mới."
            );
            return;
        }

        $state = ConversationState::forChat($chatId);
        $state->current_command = 'review';
        $state->state_data = [
            'user_id' => $user->id,
            'index' => 0,
            'correct' => 0,
            'schedule_ids' => $due->pluck('id')->all(),
        ];
        $state->save();

        // Send intro message with motivational header.
        $this->telegram->sendMessage(
            $chatId,
            "🔁 <b>ÔN TẬP HÔM NAY</b>\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "📚 Bạn có <b>" . $due->count() . " thẻ</b> cần ôn.\n"
            . "⏱ Trung bình <b>~30 giây/thẻ</b>.\n\n"
            . "💡 <b>Mẹo:</b> Thử nhớ nghĩa TRƯỚC khi bấm nút, rồi chọn mức độ tự tin:\n"
            . "  • 🔁 Lại - quên hoàn toàn\n"
            . "  • 😣 Khó - nhớ mơ hồ\n"
            . "  • 👍 Tốt - nhớ rõ\n"
            . "  • 🎉 Dễ - nhớ rất tốt\n\n"
            . "👇 Bắt đầu thẻ đầu tiên:"
        );

        $this->sendReviewCard($chatId, $due->first(), 0, $due->count());
    }

    public function sendReviewCard(string $chatId, ReviewSchedule $schedule, int $index, int $total): void
    {
        $entry = $schedule->vocabularyEntry;
        $progress = $this->progressBar($index + 1, $total);

        // Build the card content. Show meaning directly to avoid the extra tap.
        $lines = [];
        $lines[] = $progress . " <b>" . ($index + 1) . "/{$total}</b>";
        $lines[] = "";
        $lines[] = "🇬🇧 <b>{$entry->word}</b>";
        if ($entry->ipa) {
            $lines[] = "🔊 <code>{$entry->ipa}</code>";
        }
        if ($entry->pos) {
            $lines[] = "📐 <i>{$entry->pos}</i>";
        }
        $lines[] = "";
        $lines[] = "🇻🇳 <b>{$entry->meaning_vi}</b>";
        if ($entry->example_en) {
            $lines[] = "";
            $lines[] = "💬 <i>\"{$entry->example_en}\"</i>";
        }
        $lines[] = "";
        $lines[] = "<i>Mức độ bạn nhớ từ này thế nào?</i>";

        $keyboard = [
            'inline_keyboard' => [
                $this->sr->gradeKeyboard($schedule->id)[0],
                [
                    ['text' => '⏭ Bỏ qua thẻ này', 'callback_data' => "tgb:rskip:{$schedule->id}"],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, implode("\n", $lines), $keyboard);
    }

    /**
     * Build a 10-block progress bar.
     */
    private function progressBar(int $current, int $total): string
    {
        $pct = $current / $total;
        $blocks = 10;
        $filled = (int) round($pct * $blocks);
        $empty = max(0, $blocks - $filled);
        return str_repeat('🟩', $filled) . str_repeat('⬜', $empty);
    }

    private function applyReviewGrade(string $chatId, ?int $messageId, User $user, int $scheduleId, int $grade): void
    {
        $schedule = ReviewSchedule::query()
            ->where('user_id', $user->id)
            ->where('id', $scheduleId)
            ->first();

        if (! $schedule) {
            return;
        }

        $this->sr->grade($schedule, $grade);

        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $ids = $data['schedule_ids'] ?? [];
        $nextIndex = ($data['index'] ?? 0) + 1;
        $correct = ($data['correct'] ?? 0) + ($grade >= ReviewSchedule::GRADE_GOOD ? 1 : 0);

        if ($nextIndex >= count($ids)) {
            $xp = $correct * 2;
            $user->xp = ($user->xp ?? 0) + $xp;
            $user->save();

            $total = count($ids);
            $accuracy = $total > 0 ? (int) round(($correct / $total) * 100) : 0;
            $stars = $accuracy >= 90 ? '⭐⭐⭐' : ($accuracy >= 70 ? '⭐⭐' : '⭐');
            $encouragement = match (true) {
                $accuracy === 100 => "🏆 <b>Hoàn hảo!</b> Bạn nhớ tất cả!",
                $accuracy >= 80 => "🌟 Xuất sắc! Tiếp tục duy trì nhé!",
                $accuracy >= 60 => "👍 Tốt lắm! Hãy ôn thêm các thẻ yếu.",
                default => "💪 Cố gắng thêm! Ôn lại sẽ giúp nhớ lâu hơn.",
            };

            $this->telegram->sendMessage(
                $chatId,
                "🎉 <b>Hoàn thành ôn tập!</b>\n"
                . "━━━━━━━━━━━━━━━━━━━━\n\n"
                . "📊 <b>Kết quả:</b>\n"
                . "  ✅ Đúng: <b>{$correct}/{$total}</b> ({$accuracy}%)\n"
                . "  {$stars}\n\n"
                . "{$encouragement}\n\n"
                . "⚡ <b>+{$xp} XP</b>"
            );

            // Offer next-step CTAs.
            $this->telegram->sendMessage(
                $chatId,
                "Bạn muốn làm gì tiếp theo?",
                [
                    'inline_keyboard' => [
                        [
                            ['text' => '📝 Làm quiz', 'callback_data' => 'tgb:q:start'],
                            ['text' => '📚 Từ vựng mới', 'callback_data' => 'tgb:vocab-detail'],
                        ],
                        [
                            ['text' => '🏠 Menu chính', 'callback_data' => 'tgb:menu'],
                        ],
                    ],
                ]
            );

            $state->clear();
            return;
        }

        $data['index'] = $nextIndex;
        $data['correct'] = $correct;
        $state->state_data = $data;
        $state->save();

        $nextSchedule = ReviewSchedule::query()->find($ids[$nextIndex]);
        if ($nextSchedule) {
            $this->sendReviewCard($chatId, $nextSchedule, $nextIndex, count($ids));
        }
    }

    private function sendLessonDetail(string $chatId, ?int $messageId, User $user, int $lessonId): void
    {
        $lesson = \Modules\TelegramBot\Models\DailyLesson::query()
            ->where('user_id', $user->id)
            ->where('id', $lessonId)
            ->first();

        if (! $lesson) {
            return;
        }

        $words = VocabularyEntry::query()
            ->where('user_id', $user->id)
            ->where('topic_id', $lesson->topic_id)
            ->orderBy('id')
            ->get();

        $lines = ["📖 <b>Chi tiết bài học</b>\n"];
        foreach ($words as $i => $w) {
            $num = $i + 1;
            $lines[] = "<b>{$num}. {$w->word}</b> <i>({$w->pos})</i>";
            $lines[] = "🇻🇳 {$w->meaning_vi}";
            if ($w->example_en) {
                $lines[] = "✏️ <i>\"{$w->example_en}\"</i>";
            }
            $lines[] = "";
        }
        $lines[] = "Gõ /quiz để luyện tập các từ này!";

        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    /**
     * Skip a review card and move to the next one (no XP awarded).
     */
    private function skipReviewCard(string $chatId, User $user): void
    {
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $ids = $data['schedule_ids'] ?? [];
        $nextIndex = ($data['index'] ?? 0) + 1;

        if ($nextIndex >= count($ids)) {
            $this->telegram->sendMessage(
                $chatId,
                "👋 <b>Đã thoát ôn tập.</b>\n"
                . "Tiến độ của bạn đã được lưu. Gõ /review bất cứ lúc nào để tiếp tục."
            );
            $state->clear();
            return;
        }

        $data['index'] = $nextIndex;
        $state->state_data = $data;
        $state->save();

        $nextSchedule = ReviewSchedule::query()->find($ids[$nextIndex]);
        if ($nextSchedule) {
            $this->sendReviewCard($chatId, $nextSchedule, $nextIndex, count($ids));
        }
    }

    /**
     * Skip the user's current topic and promote the next locked topic in
     * the same purpose to STATUS_CURRENT. Mirrors the "⏭ Bỏ qua" button
     * on the roadmap screen.
     */
    private function skipCurrentTopic(string $chatId, User $user): void
    {
        $profile = \Modules\TelegramBot\Models\LearningProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            return;
        }

        $promoted = DB::transaction(function () use ($user, $profile) {
            $current = UserPath::query()
                ->where('user_id', $user->id)
                ->where('status', UserPath::STATUS_CURRENT)
                ->first();

            if (! $current) {
                return null;
            }

            $current->status = UserPath::STATUS_SKIPPED;
            $current->completed_at = Carbon::now();
            $current->save();

            $next = UserPath::query()
                ->where('user_id', $user->id)
                ->where('status', UserPath::STATUS_LOCKED)
                ->join('tgb_topics', 'tgb_topics.id', '=', 'tgb_user_paths.topic_id')
                ->where('tgb_topics.purpose', $profile->purpose)
                ->orderBy('tgb_topics.order_index')
                ->select('tgb_user_paths.*')
                ->first();

            if ($next) {
                $next->status = UserPath::STATUS_CURRENT;
                $next->started_at = Carbon::now();
                $next->save();
            }

            return $next;
        });

        $text = $promoted
            ? "⏭️ <b>Đã bỏ qua chủ đề hiện tại.</b>\n\nBạn đã chuyển sang chủ đề tiếp theo."
            : "⏭️ <b>Đã bỏ qua chủ đề hiện tại.</b>\n\n"
                . "Bạn đã hoàn thành tất cả chủ đề trong mục đích này. 🎉";

        $this->telegram->sendMessage(
            $chatId,
            $text,
            ['inline_keyboard' => [[['text' => '📍 Xem lộ trình', 'callback_data' => 'tgb:roadmap']]]]
        );
    }

    /**
     * Display the user's full achievements list, with locked entries
     * showing the unlock hint and unlocked entries showing the date.
     */
    private function sendAchievementsList(string $chatId, User $user): void
    {
        $unlocked = \Modules\TelegramBot\Models\UserAchievement::query()
            ->where('user_id', $user->id)
            ->orderByDesc('unlocked_at')
            ->get()
            ->keyBy('achievement_key');

        $total = count(AchievementService::CATALOG);
        $have = $unlocked->count();

        $lines = [];
        $lines[] = "🏆 <b>HUY HIỆU CỦA BẠN</b>";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "";
        $lines[] = "✨ Tiến độ: <b>{$have}/{$total}</b>";

        if ($total > 0 && $have === $total) {
            $lines[] = "🎉 <i>Bạn đã sưu tập đủ tất cả huy hiệu!</i>";
        }
        $lines[] = "";

        foreach (AchievementService::CATALOG as $key => $info) {
            $has = $unlocked->has($key);
            $status = $has ? '✅' : '🔒';
            $name = $has ? "<b>{$info['name']}</b>" : "<i>{$info['name']}</i>";
            $desc = $has ? '' : "  <i>— {$info['description']}</i>";
            $lines[] = "{$status} {$info['emoji']} {$name}  <i>(+{$info['xp']} XP)</i>{$desc}";
            if ($has) {
                $date = $unlocked[$key]->unlocked_at->format('d/m/Y');
                $lines[] = "      <i>Mở khóa ngày {$date}</i>";
            }
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🏠 Menu chính', 'callback_data' => 'tgb:menu'],
                    ['text' => '📚 Học tiếp', 'callback_data' => 'tgb:q:start'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, implode("\n", $lines), $keyboard);
    }

    /**
     * Display the main menu — a single screen with all top-level actions.
     */
    public function sendMainMenu(string $chatId, User $user): void
    {
        $profile = \Modules\TelegramBot\Models\LearningProfile::query()
            ->where('user_id', $user->id)
            ->first();

        $streak = $user->streak ?? 0;
        $xp = $user->xp ?? 0;
        $achievementCount = $this->achievements->unlockedCount($user);
        $achievementTotal = count(AchievementService::CATALOG);
        $levelInfo = $this->levels->currentLevelInfo($user);
        $levelProgress = $this->levels->progressPercent($user);

        $streakBlock = $streak > 0
            ? "🔥 Streak: <b>{$streak} ngày</b>\n"
            : "💡 Hoàn thành bài học hôm nay để bắt đầu streak!\n";

        $text = "🏠 <b>MENU CHÍNH</b>\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "👋 Xin chào, <b>{$user->name}</b>!\n"
            . "{$levelInfo['emoji']} Level: <b>{$levelInfo['level']} — {$levelInfo['name_vi']}</b>\n"
            . "⚡ Tổng XP: <b>{$xp}</b> (tiến độ level: {$levelProgress}%)\n"
            . $streakBlock
            . "🏆 Huy hiệu: <b>{$achievementCount}/{$achievementTotal}</b>\n"
            . "\n"
            . "🎯 <b>Chọn hoạt động:</b>";

        $rows = [
            [
                ['text' => '📚 Từ vựng hôm nay', 'callback_data' => 'tgb:vocab-detail'],
                ['text' => '🧠 Cấu trúc câu', 'callback_data' => 'tgb:vocab-detail'],
            ],
            [
                ['text' => '🔁 Ôn tập SR', 'callback_data' => 'tgb:rv'],
                ['text' => '📝 Quiz', 'callback_data' => 'tgb:q:start'],
            ],
            [
                ['text' => '📍 Lộ trình', 'callback_data' => 'tgb:roadmap'],
                ['text' => '⚙️ Cài đặt', 'callback_data' => 'tgb:settings'],
            ],
            [
                ['text' => '🏆 Huy hiệu của tôi', 'callback_data' => 'tgb:achievements'],
            ],
        ];

        // Premium / extra-lesson opt-in: show the on-demand lesson button
        // only for users who have been granted permission.
        if ($user->can_request_extra_lesson) {
            $rows[] = [
                ['text' => '📖 Học thêm bài', 'callback_data' => 'tgb:extra'],
            ];
        }

        $rows[] = [
            ['text' => '🎮 Mini-game', 'callback_data' => 'tgb:game'],
        ];

        $keyboard = ['inline_keyboard' => $rows];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Send an error message with a "open web" fallback button. Used when
     * the bot hits a problem (AI unavailable, missing data, etc.) so the
     * user can switch to the web UI.
     */
    public function sendErrorWithFallback(string $chatId, string $message, ?string $webUrl = null): void
    {
        $webUrl ??= url('/student/dashboard');
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌐 Mở web app', 'url' => $webUrl],
                    ['text' => '🏠 Menu', 'callback_data' => 'tgb:menu'],
                ],
            ],
        ];
        $this->telegram->sendMessage($chatId, $message, $keyboard);
    }

    /**
     * Handle `/extra` command and the `tgb:extra` menu/lesson callback.
     * Generates an on-demand lesson for the user's current topic, with
     * permission and rate-limit checks enforced by TelegramLearningService.
     */
    public function handleExtraLesson(string $chatId, User $user): void
    {
        $this->telegram->sendMessage(
            $chatId,
            "📖 <b>Đang tạo bài học mới...</b>\n\n⏳ Vui lòng đợi trong giây lát."
        );

        $result = $this->learning->sendExtraLesson($user);

        if ($result['ok']) {
            // sendDailyLesson() already sent the 3 intro/vocab/grammar
            // messages. Nothing else to do here.
            return;
        }

        $message = match ($result['reason']) {
            'no_permission' => "🔒 <b>Tính năng học thêm chưa được kích hoạt.</b>\n\n"
                . "Vui lòng liên hệ admin để được cấp quyền sử dụng.",
            'not_onboarded' => "⚠️ <b>Bạn cần hoàn tất thiết lập trước.</b>\n\n"
                . "Gõ /start để bắt đầu.",
            'paused' => "⏸ <b>Bạn đang ở trạng thái tạm dừng.</b>\n\n"
                . "Vào /settings để bật lại việc nhận bài.",
            'daily_limit' => "⏳ <b>Bạn đã học thêm " . TelegramLearningService::EXTRA_DAILY_LIMIT
                . " bài hôm nay.</b>\n\n"
                . "Hãy quay lại vào ngày mai nhé!",
            'no_link' => "🔗 Bạn cần liên kết tài khoản trước. Gõ /start.",
            default => "⚠️ Không thể tạo bài học mới. Vui lòng thử lại sau.",
        };

        $this->telegram->sendMessage(
            $chatId,
            $message,
            [
                'inline_keyboard' => [
                    [
                        ['text' => '🏠 Menu chính', 'callback_data' => 'tgb:menu'],
                        ['text' => '⚙️ Cài đặt', 'callback_data' => 'tgb:settings'],
                    ],
                ],
            ]
        );
    }
}
