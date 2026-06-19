<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Gamification\Services\GamificationService;
use Modules\Question\Models\Question;
use Modules\TelegramBot\Models\ReadingPassage;
use Modules\TelegramBot\Models\ReadingPassageQuestion;
use Modules\TelegramBot\Services\ReadingPassageService;
use Modules\TelegramBot\Services\SpacedRepetitionService;
use Tests\TestCase;

class ReadingPassageScheduleRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_attempt_can_defer_srs_grading_until_user_self_grades(): void
    {
        $user = User::factory()->create(['xp' => 0]);
        $passage = ReadingPassage::query()->create([
            'slug' => 'deferred-srs',
            'title' => 'Deferred SRS',
            'body' => 'Paris is the capital of France.',
            'difficulty' => 'easy',
            'is_active' => true,
        ]);
        $question = Question::query()->create([
            'skill' => 'reading',
            'type' => 'mcq',
            'topic' => 'Cities',
            'difficulty' => 'easy',
            'content' => [
                'question' => 'What is the capital of France?',
                'answer' => 'Paris',
                'options' => ['Paris', 'London'],
            ],
        ]);
        ReadingPassageQuestion::query()->create([
            'reading_passage_id' => $passage->id,
            'question_id' => $question->id,
            'order_index' => 1,
        ]);
        $passage->load('passageQuestions.question');

        $gamification = Mockery::mock(GamificationService::class);
        $gamification->shouldReceive('awardPoints')->once();
        $service = new ReadingPassageService(
            app(SpacedRepetitionService::class),
            $gamification,
        );

        $service->submitAttempt(
            $user,
            $passage,
            [$question->id => 'Paris'],
            null,
            null,
            false,
        );

        $review = $service->reviewFor($passage->id, $user);
        $this->assertNotNull($review);
        $this->assertSame(0, $review->repetitions);
        $this->assertNull($review->last_grade);
    }
}
