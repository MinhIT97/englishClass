<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\TelegramBot\Models\ConversationState;
use Modules\TelegramBot\Models\ReviewSchedule;
use Modules\TelegramBot\Models\UserTelegramLink;
use Modules\TelegramBot\Models\VocabularyEntry;
use Modules\TelegramBot\Services\AchievementService;
use Modules\TelegramBot\Services\LevelService;
use Modules\TelegramBot\Services\ReadingPassageService;
use Modules\TelegramBot\Services\SpacedRepetitionService;
use Modules\TelegramBot\Services\TelegramBotCommandService;
use Modules\TelegramBot\Services\TelegramGameService;
use Modules\TelegramBot\Services\TelegramLearningService;
use Modules\TelegramBot\Services\TelegramOnboardingService;
use Modules\TelegramBot\Services\TelegramQuizService;
use Modules\TelegramBot\Services\TelegramSettingsService;
use Modules\TelegramBot\Services\TextToSpeechService;
use Tests\TestCase;

class TelegramWorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_user_without_profile_can_resume_onboarding(): void
    {
        $user = User::factory()->create();
        UserTelegramLink::query()->create([
            'user_id' => $user->id,
            'telegram_chat_id' => 'onboarding-chat',
            'linked_at' => now(),
        ]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendMessage')->once()->andReturn([]);

        (new TelegramOnboardingService($telegram))
            ->startWizard('onboarding-chat', null);

        $state = ConversationState::forChat('onboarding-chat')->fresh();
        $this->assertSame('onboarding', $state->current_command);
        $this->assertSame($user->id, $state->state_data['user_id']);
    }

    public function test_purpose_confirmation_callback_reaches_confirm_handler(): void
    {
        $user = User::factory()->create();
        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('answerCallbackQuery')->once();

        $settings = Mockery::mock(TelegramSettingsService::class);
        $settings->shouldReceive('handlePurposeConfirm')
            ->once()
            ->with('settings-chat', $user->id);

        $service = $this->commandService($telegram, $settings);
        $service->handleCallback(
            'settings-chat',
            'callback-id',
            'tgb:settings:purpose:confirm:business',
            null,
            $user,
        );
    }

    public function test_stale_review_callback_does_not_advance_the_current_session(): void
    {
        $user = User::factory()->create();
        $firstEntry = $this->vocabulary($user, 'first');
        $secondEntry = $this->vocabulary($user, 'second');
        $first = $this->schedule($user, $firstEntry);
        $second = $this->schedule($user, $secondEntry);

        ConversationState::query()->create([
            'telegram_chat_id' => 'review-chat',
            'current_command' => 'review',
            'state_data' => [
                'user_id' => $user->id,
                'index' => 0,
                'correct' => 0,
                'schedule_ids' => [$first->id, $second->id],
            ],
        ]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('answerCallbackQuery')->once();

        $service = $this->commandService(
            $telegram,
            Mockery::mock(TelegramSettingsService::class),
            app(SpacedRepetitionService::class),
        );
        $service->handleCallback(
            'review-chat',
            'callback-id',
            "tgb:r:{$second->id}:3",
            null,
            $user,
        );

        $this->assertSame(0, $second->fresh()->repetitions);
        $this->assertSame(
            0,
            ConversationState::forChat('review-chat')->fresh()->state_data['index'],
        );
    }

    public function test_welcome_back_uses_the_previous_interaction_time(): void
    {
        $user = User::factory()->create(['streak' => 2]);
        UserTelegramLink::query()->create([
            'user_id' => $user->id,
            'telegram_chat_id' => 'idle-chat',
            'linked_at' => now()->subWeek(),
            'last_interaction_at' => Carbon::now()->subDays(2),
        ]);

        $messages = [];
        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendMessage')
            ->twice()
            ->andReturnUsing(function ($chatId, $text, $keyboard = []) use (&$messages) {
                $messages[] = compact('chatId', 'text', 'keyboard');

                return [];
            });

        $this->commandService($telegram)->handleFreeText('idle-chat', 'hello', $user);

        $this->assertSame('tgb:rv', $messages[0]['keyboard']['inline_keyboard'][0][0]['callback_data']);
        $this->assertTrue(
            UserTelegramLink::query()
                ->where('user_id', $user->id)
                ->value('last_interaction_at')
                ->greaterThan(Carbon::now()->subMinute()),
        );
    }

    private function commandService(
        TelegramService $telegram,
        ?TelegramSettingsService $settings = null,
        ?SpacedRepetitionService $sr = null,
    ): TelegramBotCommandService {
        return new TelegramBotCommandService(
            $telegram,
            Mockery::mock(TelegramOnboardingService::class),
            Mockery::mock(TelegramQuizService::class),
            $sr ?? Mockery::mock(SpacedRepetitionService::class),
            Mockery::mock(TelegramLearningService::class),
            Mockery::mock(TelegramGameService::class),
            $settings ?? Mockery::mock(TelegramSettingsService::class),
            Mockery::mock(AchievementService::class),
            Mockery::mock(LevelService::class),
            Mockery::mock(TextToSpeechService::class),
            Mockery::mock(ReadingPassageService::class),
        );
    }

    private function vocabulary(User $user, string $word): VocabularyEntry
    {
        return VocabularyEntry::query()->create([
            'user_id' => $user->id,
            'word' => $word,
            'meaning_vi' => $word,
        ]);
    }

    private function schedule(User $user, VocabularyEntry $entry): ReviewSchedule
    {
        return ReviewSchedule::query()->create([
            'user_id' => $user->id,
            'vocabulary_entry_id' => $entry->id,
            'ease_factor' => 2.5,
            'interval_days' => 0,
            'repetitions' => 0,
            'next_review_at' => now(),
        ]);
    }
}
