<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;
use Modules\TelegramBot\Models\ConversationState;
use Modules\TelegramBot\Models\VocabularyEntry;

/**
 * Mini-games for vocabulary practice.
 *
 * Currently implemented:
 *  - Word scramble: shuffle the letters of a word, user types the unscrambled
 *    answer as a free-text message. Hint = number of letters.
 *  - Match pairs: show 4 words and 4 meanings in a 4x4 grid; user picks the
 *    correct pair. (Callback-based.)
 *  - Sentence builder: take an example sentence with one word masked out and
 *    let the user fill in the missing word.
 */
class TelegramGameService
{
    public const GAME_SCRAMBLE = 'scramble';
    public const GAME_MATCH = 'match';
    public const GAME_SENTENCE = 'sentence';

    public function __construct(
        private readonly TelegramService $telegram,
    ) {
    }

    /**
     * Entry point for /game. Shows the menu, then dispatches to a random game
     * unless the user has picked one.
     */
    public function showMenu(string $chatId): void
    {
        $text = "🎮 <b>MINI-GAME</b>\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "Chọn trò chơi để luyện tập:\n\n"
            . "🔀 <b>Word Scramble</b> - Sắp xếp lại các chữ cái\n"
            . "🧩 <b>Match Pairs</b> - Ghép từ với nghĩa đúng\n"
            . "📝 <b>Sentence Builder</b> - Điền từ vào câu\n\n"
            . "💡 Mỗi lần đúng: <b>+5 XP</b>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔀 Word Scramble', 'callback_data' => 'tgb:game:scramble'],
                ],
                [
                    ['text' => '🧩 Match Pairs', 'callback_data' => 'tgb:game:match'],
                ],
                [
                    ['text' => '📝 Sentence Builder', 'callback_data' => 'tgb:game:sentence'],
                ],
                [
                    ['text' => '🎲 Chọn ngẫu nhiên', 'callback_data' => 'tgb:game:random'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Start a game. Used both by the menu and by the "random" button.
     */
    public function startGame(string $chatId, User $user, string $type): void
    {
        if ($type === 'random') {
            $type = collect([self::GAME_SCRAMBLE, self::GAME_MATCH, self::GAME_SENTENCE])->random();
        }

        $words = VocabularyEntry::query()
            ->where('user_id', $user->id)
            ->inRandomOrder()
            ->limit(5)
            ->get();

        if ($words->isEmpty()) {
            $this->telegram->sendMessage(
                $chatId,
                "📭 <b>Bạn chưa có từ vựng để chơi game.</b>\n\n"
                . "Hãy đợi bài học đầu tiên nhé!",
                [
                    'inline_keyboard' => [
                        [
                            ['text' => '🌐 Mở web', 'url' => url('/student/flashcards')],
                            ['text' => '🏠 Menu', 'callback_data' => 'tgb:menu'],
                        ],
                    ],
                ]
            );
            return;
        }

        $state = ConversationState::forChat($chatId);
        $state->current_command = 'game';
        $state->state_data = [
            'user_id' => $user->id,
            'type' => $type,
            'index' => 0,
            'score' => 0,
            'entry_ids' => $words->pluck('id')->all(),
        ];
        $state->save();

        $this->sendRound($chatId, $user, 0);
    }

    /**
     * Send a single round of the active game.
     */
    public function sendRound(string $chatId, User $user, int $index): void
    {
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $type = $data['type'] ?? self::GAME_SCRAMBLE;
        $entryIds = $data['entry_ids'] ?? [];

        if (! isset($entryIds[$index])) {
            $this->finishGame($chatId, $data);
            return;
        }

        $entry = VocabularyEntry::query()->find($entryIds[$index]);
        if (! $entry) {
            $this->finishGame($chatId, $data);
            return;
        }

        switch ($type) {
            case self::GAME_SCRAMBLE:
                $this->sendScramble($chatId, $entry, $index, count($entryIds));
                break;
            case self::GAME_MATCH:
                $pool = VocabularyEntry::query()
                    ->whereIn('id', $entryIds)
                    ->get();
                $this->sendMatch($chatId, $entry, $pool, $index, count($entryIds));
                break;
            case self::GAME_SENTENCE:
                $this->sendSentence($chatId, $entry, $index, count($entryIds));
                break;
        }
    }

    public function handleCallback(string $chatId, User $user, int $index, int $chosen): void
    {
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $type = $data['type'] ?? self::GAME_SCRAMBLE;

        if ($type !== self::GAME_MATCH) {
            return;
        }

        // Guard against stale/duplicate callbacks.
        if (($data['index'] ?? -1) !== $index) {
            return;
        }

        $entryIds = $data['entry_ids'] ?? [];
        $entry = VocabularyEntry::query()->find($entryIds[$index] ?? 0);
        if (! $entry) {
            return;
        }

        // Use the EXACT options array that was displayed to the user,
        // not a fresh shuffle.
        $options = $data['match_options'] ?? [];
        $correct = $entry->meaning_vi;
        $isCorrect = ($options[$chosen] ?? null) === $correct;

        $this->recordResult($chatId, $user, $data, $index, $isCorrect, $options, $chosen);
    }

    /**
     * Handle free-text input (used by word scramble and sentence builder).
     */
    public function handleAnswer(string $chatId, User $user, string $text): void
    {
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $type = $data['type'] ?? self::GAME_SCRAMBLE;

        $entryIds = $data['entry_ids'] ?? [];
        $index = $data['index'] ?? 0;

        // Skip: advance without scoring or showing failure feedback.
        if ($text === '__skip__') {
            $this->advanceRound($chatId, $user, $data, $index);
            return;
        }

        $entry = VocabularyEntry::query()->find($entryIds[$index] ?? 0);
        if (! $entry) {
            return;
        }

        $text = strtolower(trim($text));
        $expected = strtolower($entry->word);

        $isCorrect = match ($type) {
            self::GAME_SCRAMBLE => $text === $expected,
            self::GAME_SENTENCE => $text === $expected,
            default => false,
        };

        $this->recordResult($chatId, $user, $data, $index, $isCorrect, [$text], null);
    }

    private function advanceRound(string $chatId, User $user, array $data, int $index): void
    {
        $next = $index + 1;
        if ($next >= count($data['entry_ids'] ?? [])) {
            $this->finishGame($chatId, $data);
            return;
        }

        $data['index'] = $next;
        $state = ConversationState::forChat($chatId);
        $state->state_data = $data;
        $state->save();

        $this->telegram->sendMessage($chatId, "⏭ <b>Đã bỏ qua.</b>");
        $this->sendRound($chatId, $user, $next);
    }

    private function recordResult(string $chatId, User $user, array $data, int $index, bool $isCorrect, array $options, ?int $chosen): void
    {
        $newScore = ($data['score'] ?? 0) + ($isCorrect ? 1 : 0);

        if ($isCorrect) {
            $user->xp = ($user->xp ?? 0) + 5;
            $user->save();
            $this->telegram->sendMessage(
                $chatId,
                "✅ <b>Đúng rồi!</b> +5 XP"
            );
        } else {
            $entryIds = $data['entry_ids'] ?? [];
            $entry = VocabularyEntry::query()->find($entryIds[$index] ?? 0);
            $correctText = $entry?->word ?? '';
            $this->telegram->sendMessage(
                $chatId,
                "❌ <b>Sai rồi.</b> Đáp án: <b>{$correctText}</b>"
            );
        }

        $next = $index + 1;
        if ($next >= count($data['entry_ids'] ?? [])) {
            $data['score'] = $newScore;
            $this->finishGame($chatId, $data);
            return;
        }

        $data['index'] = $next;
        $data['score'] = $newScore;
        $state = ConversationState::forChat($chatId);
        $state->state_data = $data;
        $state->save();

        $this->sendRound($chatId, $user, $next);
    }

    private function sendScramble(string $chatId, VocabularyEntry $entry, int $index, int $total): void
    {
        $word = strtolower($entry->word);
        $letters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        // Skip scrambling for very short words.
        if (count($letters) <= 3) {
            $scrambled = $word;
        } else {
            do {
                shuffle($letters);
                $scrambled = implode('', $letters);
            } while ($scrambled === $word);
        }

        $hint = ! empty($entry->pos) ? "Loại từ: <i>{$entry->pos}</i>" : '';

        $text = "🎮 <b>WORD SCRAMBLE</b>  " . ($index + 1) . "/{$total}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "🔀 Sắp xếp lại các chữ cái:\n\n"
            . "   <code>" . strtoupper($scrambled) . "</code>\n\n"
            . "💡 {$hint}\n"
            . "📏 Số chữ cái: <b>" . mb_strlen($word) . "</b>\n\n"
            . "✍️ Gõ đáp án của bạn:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '💡 Gợi ý', 'callback_data' => "tgb:ghint:{$entry->id}"],
                    ['text' => '⏭ Bỏ qua', 'callback_data' => "tgb:gskip"],
                ],
                [
                    ['text' => '❌ Thoát game', 'callback_data' => 'tgb:gexit'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function sendMatch(string $chatId, VocabularyEntry $entry, $pool, int $index, int $total): void
    {
        $options = $pool->pluck('meaning_vi')->shuffle()->values()->all();

        // Persist options to state so handleCallback reads the SAME order.
        $state = ConversationState::forChat($chatId);
        $data = (array) $state->state_data;
        $data['match_options'] = $options;
        $state->state_data = $data;
        $state->save();

        $text = "🎮 <b>MATCH PAIRS</b>  " . ($index + 1) . "/{$total}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "🧩 Chọn nghĩa đúng của từ:\n\n"
            . "   <b>{$entry->word}</b>";

        $keyboard = ['inline_keyboard' => []];
        foreach ($options as $i => $opt) {
            $keyboard['inline_keyboard'][] = [
                [
                    'text' => ($i + 1) . '. ' . $opt,
                    'callback_data' => "tgb:gmatch:{$index}:{$i}",
                ],
            ];
        }
        $keyboard['inline_keyboard'][] = [
            [
                'text' => '❌ Thoát game',
                'callback_data' => 'tgb:gexit',
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function sendSentence(string $chatId, VocabularyEntry $entry, int $index, int $total): void
    {
        $example = $entry->example_en ?: '';
        if ($example !== '') {
            // Replace only the first occurrence so compounds/multi-word
            // phrases don't get mangled.
            $pattern = '/' . preg_quote($entry->word, '/') . '/iu';
            $masked = preg_replace($pattern, '________', $example, 1);
            if ($masked === $example) {
                // The stored example does not demonstrate this vocabulary
                // entry. Never hide an unrelated word while grading against
                // the entry word; use an explicit definition fallback.
                $masked = '________ — "' . $entry->meaning_vi . '"';
            }
        } else {
            // Fallback: no example sentence — build one from the word + meaning.
            $masked = '________ — "' . $entry->meaning_vi . '"';
        }

        $text = "🎮 <b>SENTENCE BUILDER</b>  " . ($index + 1) . "/{$total}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "📝 Điền từ còn thiếu vào câu:\n\n"
            . "   <i>\"{$masked}\"</i>\n\n"
            . "💡 Nghĩa: <b>{$entry->meaning_vi}</b>\n\n"
            . "✍️ Gõ từ còn thiếu:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '⏭ Bỏ qua', 'callback_data' => 'tgb:gskip'],
                    ['text' => '❌ Thoát game', 'callback_data' => 'tgb:gexit'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function finishGame(string $chatId, array $data): void
    {
        $score = (int) ($data['score'] ?? 0);
        $total = count($data['entry_ids'] ?? []);
        $pct = $total > 0 ? (int) round(($score / $total) * 100) : 0;
        $xp = $score * 5;

        $stars = $pct >= 90 ? '⭐⭐⭐' : ($pct >= 60 ? '⭐⭐' : '⭐');
        $message = match (true) {
            $pct === 100 => "🏆 <b>Hoàn hảo!</b> Bạn đã trả lời đúng tất cả!",
            $pct >= 70 => "🌟 Xuất sắc! Tiếp tục phát huy!",
            $pct >= 40 => "👍 Tốt! Luyện thêm để nhớ lâu hơn.",
            default => "💪 Cố gắng hơn nữa nhé!",
        };

        ConversationState::forChat($chatId)->clear();

        $this->telegram->sendMessage(
            $chatId,
            "🎉 <b>Hoàn thành game!</b>\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n"
            . "📊 Điểm: <b>{$score}/{$total}</b> ({$pct}%) {$stars}\n"
            . "⚡ Tổng XP: <b>+{$xp}</b>\n\n"
            . "{$message}",
            [
                'inline_keyboard' => [
                    [
                        ['text' => '🔁 Chơi lại', 'callback_data' => 'tgb:game:random'],
                        ['text' => '📝 Làm quiz', 'callback_data' => 'tgb:q:start'],
                    ],
                    [
                        ['text' => '🏠 Menu', 'callback_data' => 'tgb:menu'],
                    ],
                ],
            ]
        );
    }
}
