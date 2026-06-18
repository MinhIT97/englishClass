<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Models\ConversationState;
use Modules\TelegramBot\Models\QuizAttempt;
use Modules\TelegramBot\Models\ReviewSchedule;
use Modules\TelegramBot\Models\VocabularyEntry;

/**
 * Builds and grades interactive quizzes inside Telegram.
 */
class TelegramQuizService
{
    public function __construct(
        private readonly TelegramService $telegram,
        private readonly AchievementService $achievements,
        private readonly LevelService $levels,
    ) {
    }

    /**
     * Start a 5-question quiz on the user's recent vocabulary.
     */
    public function startQuiz(string $chatId, User $user, int $questionCount = 5): void
    {
        $words = VocabularyEntry::query()
            ->where('user_id', $user->id)
            ->with('reviewSchedule')
            ->get()
            ->sortByDesc(function ($w) {
                // Prefer SR-due words; fall back to newest.
                $due = $w->reviewSchedule && (
                    $w->reviewSchedule->next_review_at === null
                    || $w->reviewSchedule->next_review_at->isPast()
                );
                return $due ? 2 : 1;
            })
            ->take($questionCount)
            ->values();

        if ($words->isEmpty()) {
            $this->telegram->sendMessage(
                $chatId,
                "📭 <b>Bạn chưa có từ vựng nào để làm quiz.</b>\n\n"
                . "Hãy đợi bài học hôm nay tới giờ đã chọn, hoặc vào web app để học trước.",
                [
                    'inline_keyboard' => [
                        [
                            ['text' => '🌐 Mở web app', 'url' => url('/student/flashcards')],
                            ['text' => '📚 Xem từ vựng', 'callback_data' => 'tgb:vocab-detail'],
                        ],
                    ],
                ]
            );
            return;
        }

        $questions = [];
        foreach ($words as $word) {
            $questions[] = $this->buildMultipleChoice($word, $words->all());
        }

        $state = ConversationState::forChat($chatId);
        $state->current_command = 'quiz';
        $state->state_data = [
            'user_id' => $user->id,
            'index' => 0,
            'score' => 0,
            'questions' => $questions,
        ];
        $state->save();

        $this->sendQuestion($chatId, 0, $questions);
    }

    public function sendQuestion(string $chatId, int $index, array $questions): void
    {
        if (! isset($questions[$index])) {
            return;
        }

        $q = $questions[$index];
        $text = "❓ <b>Câu " . ($index + 1) . "/" . count($questions) . ":</b>\n"
            . "Nghĩa của <b>{$q['word']}</b> là gì?";

        $keyboard = ['inline_keyboard' => []];
        foreach ($q['options'] as $i => $opt) {
            $keyboard['inline_keyboard'][] = [
                [
                    'text' => $opt,
                    'callback_data' => "tgb:qa:{$index}:{$i}",
                ],
            ];
        }

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    public function gradeAnswer(string $chatId, int $index, int $chosen, User $user): void
    {
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $questions = $data['questions'] ?? [];
        $q = $questions[$index] ?? null;

        if (! $q) {
            return;
        }

        $correct = (int) $q['correct_index'] === $chosen;
        $xp = $correct ? 5 : 0;

        DB::transaction(function () use ($user, $q, $chosen, $correct, $xp) {
            QuizAttempt::query()->create([
                'user_id' => $user->id,
                'vocabulary_entry_id' => $q['entry_id'],
                'quiz_type' => QuizAttempt::TYPE_MULTIPLE_CHOICE,
                'question_payload' => ['word' => $q['word'], 'options' => $q['options']],
                'user_answer' => $q['options'][$chosen] ?? null,
                'is_correct' => $correct,
                'xp_awarded' => $xp,
                'attempted_at' => now(),
            ]);

            if ($correct) {
                $user->xp = ($user->xp ?? 0) + $xp;
                $user->save();
            }
        });

        $feedback = $correct
            ? "✅ <b>Chính xác!</b> +{$xp} XP"
            : "❌ Sai rồi. Đáp án đúng: <b>{$q['options'][$q['correct_index']]}</b>";

        $this->telegram->sendMessage($chatId, $feedback);

        $next = $index + 1;
        if ($next >= count($questions)) {
            $this->finishQuiz($chatId, $data);
            return;
        }

        $data['index'] = $next;
        $data['score'] = ($data['score'] ?? 0) + ($correct ? 1 : 0);
        $state->state_data = $data;
        $state->save();

        $this->sendQuestion($chatId, $next, $questions);
    }

    private function finishQuiz(string $chatId, array $data): void
    {
        $score = ($data['score'] ?? 0);
        $total = count($data['questions'] ?? []);
        $perfect = $score === $total && $total > 0;

        $user = User::find($data['user_id']);
        $xpBefore = $user ? ($user->xp ?? 0) : 0;

        if ($perfect && $user) {
            $user->xp = ($user->xp ?? 0) + 20;
            $user->save();
            $score += 20;
        }

        ConversationState::forChat($chatId)->clear();

        $this->telegram->sendMessage(
            $chatId,
            "🎉 <b>Hoàn thành quiz!</b>\n"
            . "✅ Đúng: {$score}/{$total}\n"
            . "⚡ Tổng XP: +" . ($perfect ? 25 : $score * 5)
        );

        // Trigger achievement check for quiz results (best-effort).
        if ($user) {
            try {
                $unlocked = $this->achievements->checkAndUnlock(
                    $user,
                    'quiz_finished',
                    ['perfect' => $perfect]
                );
                if (! empty($unlocked)) {
                    $this->achievements->celebrate($chatId, $user, $unlocked);
                }
            } catch (\Throwable $e) {
                // Don't break quiz completion if achievement check fails.
                Log::warning('[TelegramBot] quiz achievement check failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Level-up check (after all XP grants are done).
            try {
                $levelUp = $this->levels->checkLevelUp($user, $xpBefore);
                if (! empty($levelUp['celebrated'])) {
                    $this->levels->celebrate($chatId, $user, $levelUp);
                }
            } catch (\Throwable $e) {
                Log::warning('[TelegramBot] quiz level check failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildMultipleChoice(VocabularyEntry $word, array $pool): array
    {
        $correct = $word->meaning_vi;
        $distractors = [];
        foreach ($pool as $other) {
            if ($other->id === $word->id) {
                continue;
            }
            $distractors[] = $other->meaning_vi;
            if (count($distractors) >= 3) {
                break;
            }
        }

        // Pad if pool is too small.
        while (count($distractors) < 3) {
            $distractors[] = '(khác)';
        }

        $options = array_merge([$correct], array_slice($distractors, 0, 3));
        shuffle($options);
        $correctIndex = array_search($correct, $options, true);

        return [
            'entry_id' => $word->id,
            'word' => $word->word,
            'options' => $options,
            'correct_index' => $correctIndex,
        ];
    }
}
