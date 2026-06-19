<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\TelegramBot\Models\ConversationState;
use Modules\TelegramBot\Models\VocabularyEntry;
use Modules\TelegramBot\Services\TelegramGameService;
use Tests\TestCase;

class TelegramGameServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_answering_a_round_persists_the_next_round_index(): void
    {
        $user = User::factory()->create(['xp' => 0]);
        $first = VocabularyEntry::query()->create([
            'user_id' => $user->id,
            'word' => 'confirm',
            'meaning_vi' => 'xác nhận',
            'example_en' => 'Please confirm the new project plan.',
        ]);
        $second = VocabularyEntry::query()->create([
            'user_id' => $user->id,
            'word' => 'regarding',
            'meaning_vi' => 'về việc',
            'example_en' => 'I am writing regarding the new project plan.',
        ]);

        ConversationState::query()->create([
            'telegram_chat_id' => '123456',
            'current_command' => 'game',
            'state_data' => [
                'user_id' => $user->id,
                'type' => TelegramGameService::GAME_SENTENCE,
                'index' => 0,
                'score' => 0,
                'entry_ids' => [$first->id, $second->id],
            ],
        ]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendMessage')->twice()->andReturn([]);

        $service = new TelegramGameService($telegram);
        $service->handleAnswer('123456', $user, 'confirm');

        $state = ConversationState::query()
            ->where('telegram_chat_id', '123456')
            ->firstOrFail();

        $this->assertSame(1, $state->state_data['index']);
        $this->assertSame(1, $state->state_data['score']);
        $this->assertSame(5, $user->fresh()->xp);
    }

    public function test_sentence_round_does_not_mask_an_unrelated_example_word(): void
    {
        $user = User::factory()->create();
        $entry = VocabularyEntry::query()->create([
            'user_id' => $user->id,
            'word' => 'regarding',
            'meaning_vi' => 'về việc',
            'example_en' => 'Please confirm the project plan.',
        ]);
        ConversationState::query()->create([
            'telegram_chat_id' => 'sentence-chat',
            'current_command' => 'game',
            'state_data' => [
                'user_id' => $user->id,
                'type' => TelegramGameService::GAME_SENTENCE,
                'index' => 0,
                'score' => 0,
                'entry_ids' => [$entry->id],
            ],
        ]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text) => $chatId === 'sentence-chat'
                && str_contains($text, '________')
                && ! str_contains($text, 'Please ________ the project plan.'))
            ->andReturn([]);

        (new TelegramGameService($telegram))->sendRound('sentence-chat', $user, 0);
    }
}
