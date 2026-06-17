<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
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
            case 'help':
                $this->sendHelp($chatId);
                break;

            case 'onb':
                if (! $user) { $this->requireLink($chatId); return; }
                $step = $parts[2] ?? '';
                $value = $parts[3] ?? '';
                if ($step === 'purpose') {
                    $this->onboarding->handlePurpose($chatId, $value, $user->id);
                } elseif ($step === 'level') {
                    $this->onboarding->handleLevel($chatId, $value, $user->id);
                } elseif ($step === 'hour') {
                    $this->onboarding->handleHour($chatId, (int) $value, $user->id);
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
                $this->sendSettings($chatId, $user);
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
        $text = "📚 <b>Danh sách lệnh</b>\n\n"
            . "/start [CODE] - Liên kết tài khoản\n"
            . "/vocab - Xem từ vựng hôm nay\n"
            . "/grammar - Xem cấu trúc câu hôm nay\n"
            . "/quiz - Làm bài quiz 5 câu\n"
            . "/review - Ôn tập từ vựng (Spaced Repetition)\n"
            . "/roadmap - Xem lộ trình học tập\n"
            . "/settings - Cài đặt mục đích & giờ nhận bài\n"
            . "/help - Xem hướng dẫn này";
        $this->telegram->sendMessage($chatId, $text);
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
            $this->telegram->sendMessage($chatId, "📭 Bạn chưa có lộ trình. Hoàn tất onboarding bằng /start.");
            return;
        }

        $lines = ["📍 <b>Lộ trình học tập</b>"];
        $completed = 0;
        foreach ($paths->take(10) as $p) {
            $icon = match ($p->status) {
                UserPath::STATUS_COMPLETED => '✅',
                UserPath::STATUS_CURRENT => '🔵',
                UserPath::STATUS_SKIPPED => '⏭️',
                default => '🔒',
            };
            $lines[] = "{$icon} {$p->topic->name_vi} <i>({$p->topic->name_en})</i>";
            if ($p->status === UserPath::STATUS_COMPLETED) {
                $completed++;
            }
        }

        $lines[] = "";
        $lines[] = "📊 Hoàn thành: {$completed}/" . $paths->count();

        $this->telegram->sendMessage($chatId, implode("\n", $lines));
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
            $this->telegram->sendMessage($chatId, "🎉 Không có thẻ nào cần ôn. Tốt lắm!");
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

        $this->sendReviewCard($chatId, $due->first(), 0, $due->count());
    }

    public function sendReviewCard(string $chatId, ReviewSchedule $schedule, int $index, int $total): void
    {
        $entry = $schedule->vocabularyEntry;
        $text = "🃏 <b>Thẻ ôn tập " . ($index + 1) . "/{$total}</b>\n\n"
            . "🇬🇧 <b>{$entry->word}</b>\n"
            . ($entry->ipa ? "<code>{$entry->ipa}</code>\n" : '')
            . ($entry->pos ? "<i>({$entry->pos})</i>\n" : '')
            . "\nBạn nhớ nghĩa của từ này không?";

        $this->telegram->sendMessage(
            $chatId,
            $text,
            ['inline_keyboard' => [
                [
                    ['text' => '👁 Xem nghĩa', 'callback_data' => "tgb:rshow:{$schedule->id}"],
                ],
                $this->sr->gradeKeyboard($schedule->id)[0],
            ]]
        );
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

            $this->telegram->sendMessage(
                $chatId,
                "🎉 <b>Hoàn thành ôn tập!</b>\n"
                . "✅ Đúng: {$correct}/" . count($ids) . "\n"
                . "⚡ +{$xp} XP"
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
}
