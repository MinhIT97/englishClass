<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Models\ConversationState;
use Modules\TelegramBot\Models\QuizAttempt;
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
        // Prefer SR-due words first, then newest — all at DB level to
        // avoid loading every word the user has ever learned into memory.
        $words = VocabularyEntry::query()
            ->where('tgb_vocabulary_entries.user_id', $user->id)
            ->leftJoin('tgb_review_schedules as rs', function ($join) use ($user) {
                $join->on('tgb_vocabulary_entries.id', '=', 'rs.vocabulary_entry_id')
                    ->where('rs.user_id', '=', $user->id);
            })
            ->select('tgb_vocabulary_entries.*')
            ->orderByRaw("
                CASE
                    WHEN rs.next_review_at IS NULL OR rs.next_review_at <= NOW()
                    THEN 0
                    ELSE 1
                END
            ")
            ->orderByDesc('tgb_vocabulary_entries.id')
            ->limit($questionCount)
            ->get();

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

        $allWords = $words->all();
        $fbQuota = min(2, $words->count());
        $questions = [];

        foreach ($allWords as $word) {
            if (count($questions) >= $questionCount) {
                break;
            }
            // Build fill-in-the-blank for up to 2 words that have example
            // sentences; remaining questions stay multiple-choice.
            if ($fbQuota > 0 && ! empty($word->example_en)) {
                $questions[] = $this->buildFillBlank($word, $allWords);
                $fbQuota--;
            } else {
                $questions[] = $this->buildMultipleChoice($word, $allWords);
            }
        }
        // Interleave question types so the user doesn't get 2 fills in a row.
        shuffle($questions);

        $state = ConversationState::forChat($chatId);
        $state->current_command = 'quiz';
        $state->state_data = [
            'user_id' => $user->id,
            'index' => 0,
            'score' => 0,
            'xp_before' => $user->xp ?? 0, // capture BEFORE per-question XP grants
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
        $type = $q['type'] ?? 'multiple_choice';
        $total = count($questions);

        if ($type === 'fill_blank') {
            $text = "❓ <b>Câu " . ($index + 1) . "/{$total}:</b>\n"
                . "Điền từ còn thiếu vào câu:\n\n"
                . "   <i>\"" . e($q['prompt']) . "\"</i>\n\n"
                . "💡 Nghĩa: <b>" . e($q['meaning_vi']) . "</b>";
        } else {
            $text = "❓ <b>Câu " . ($index + 1) . "/{$total}:</b>\n"
                . "Nghĩa của <b>" . e($q['word']) . "</b> là gì?";
        }

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

        // Guard: ignore stale/duplicate callbacks from rapid double-taps.
        $currentIndex = $data['index'] ?? 0;
        if ($index !== $currentIndex || ! isset($questions[$index])) {
            return;
        }

        $q = $questions[$index];
        $correct = (int) $q['correct_index'] === $chosen;
        $xp = $correct ? 5 : 0;
        $quizType = ($q['type'] ?? 'multiple_choice') === 'fill_blank'
            ? QuizAttempt::TYPE_FILL_BLANK
            : QuizAttempt::TYPE_MULTIPLE_CHOICE;

        DB::transaction(function () use ($user, $q, $chosen, $correct, $xp, $quizType) {
            QuizAttempt::query()->create([
                'user_id' => $user->id,
                'vocabulary_entry_id' => $q['entry_id'],
                'quiz_type' => $quizType,
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

        // Update score BEFORE checking completion — otherwise the last
        // question's result is never included.
        $data['score'] = ($data['score'] ?? 0) + ($correct ? 1 : 0);
        $next = $index + 1;

        if ($next >= count($questions)) {
            $data['index'] = $next;
            $state->state_data = $data;
            $state->save();
            $this->finishQuiz($chatId, $data);
            return;
        }

        $data['index'] = $next;
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
        $xpBefore = $data['xp_before'] ?? ($user ? ($user->xp ?? 0) : 0);

        // Perfect-score bonus (already granted per-question 5 XP in gradeAnswer).
        if ($perfect && $user) {
            $user->xp = ($user->xp ?? 0) + 20;
            $user->save();
        }

        ConversationState::forChat($chatId)->clear();

        $totalXpEarned = ($score * 5) + ($perfect ? 20 : 0);

        $this->telegram->sendMessage(
            $chatId,
            "🎉 <b>Hoàn thành quiz!</b>\n"
            . "✅ Đúng: {$score}/{$total}\n"
            . "⚡ Tổng XP: +{$totalXpEarned}"
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
            // Skip empty or duplicate meanings.
            if (empty($other->meaning_vi) || $other->meaning_vi === $correct) {
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
            'type' => 'multiple_choice',
            'word' => $word->word,
            'options' => $options,
            'correct_index' => $correctIndex,
        ];
    }

    /**
     * Build a fill-in-the-blank question from a word's example sentence.
     * Falls back to a simple definition gap-fill when no example exists.
     */
    private function buildFillBlank(VocabularyEntry $word, array $pool): array
    {
        $example = $word->example_en ?: '';
        $wordLower = mb_strtolower($word->word);

        if ($example !== '') {
            $masked = str_ireplace($word->word, '________', $example);
            // Avoid double-replace in compound words.
            if ($masked === $example) {
                $masked = str_ireplace($wordLower, '________', $example);
            }
        } else {
            // Fallback: "Từ ________ có nghĩa là {meaning_vi}"
            $masked = 'Từ ________ có nghĩa là "' . $word->meaning_vi . '"';
        }

        $correct = $word->word;
        $distractors = [];
        foreach ($pool as $other) {
            if ($other->id === $word->id || empty($other->word)) {
                continue;
            }
            $distractors[] = $other->word;
            if (count($distractors) >= 3) {
                break;
            }
        }
        while (count($distractors) < 3) {
            $distractors[] = '(khác)';
        }

        $options = array_merge([$correct], array_slice($distractors, 0, 3));
        shuffle($options);
        $correctIndex = array_search($correct, $options, true);

        return [
            'entry_id' => $word->id,
            'type' => 'fill_blank',
            'prompt' => $masked,
            'meaning_vi' => $word->meaning_vi,
            'word' => $word->word,
            'options' => $options,
            'correct_index' => $correctIndex,
        ];
    }
}
