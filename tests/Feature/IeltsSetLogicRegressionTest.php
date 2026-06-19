<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\IeltsSet\Models\IeltsSet;
use Modules\IeltsSet\Models\IeltsSetSection;
use Modules\Question\Models\Question;
use Tests\TestCase;

class IeltsSetLogicRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resubmitting_a_section_does_not_award_practice_xp_twice(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'xp' => 0,
        ]);
        [$set, $section, $question] = $this->setWithQuestion(true);
        IeltsSetSection::query()->create([
            'ielts_set_id' => $set->id,
            'skill' => 'reading',
            'title' => 'Second section',
            'section_order' => 2,
            'time_limit_minutes' => 20,
        ]);

        $payload = ['answers' => [$question->id => 'Paris']];

        $this->actingAs($user)
            ->post(route('student.sets.section.submit', [$set, $section]), $payload)
            ->assertRedirect();
        $this->actingAs($user)
            ->post(route('student.sets.section.submit', [$set, $section]), $payload)
            ->assertRedirect();

        $this->assertSame(0, $user->fresh()->xp);
        $this->assertDatabaseCount('user_answers', 0);
        $this->assertDatabaseCount('ielts_set_attempt_answers', 1);
    }

    public function test_unpublished_set_sections_are_not_accessible(): void
    {
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);
        [$set, $section] = $this->setWithQuestion(false);

        $this->actingAs($user)
            ->get(route('student.sets.section', [$set, $section]))
            ->assertNotFound();
    }

    private function setWithQuestion(bool $published): array
    {
        $set = IeltsSet::query()->create([
            'title' => 'Regression set',
            'slug' => 'regression-set-'.uniqid(),
            'set_type' => 'practice',
            'difficulty' => 'medium',
            'is_published' => $published,
        ]);
        $section = IeltsSetSection::query()->create([
            'ielts_set_id' => $set->id,
            'skill' => 'reading',
            'title' => 'Reading',
            'section_order' => 1,
            'time_limit_minutes' => 20,
        ]);
        $question = Question::query()->create([
            'skill' => 'reading',
            'type' => 'gap_fill',
            'topic' => 'Cities',
            'difficulty' => 'easy',
            'content' => [
                'question' => 'What is the capital of France?',
                'answer' => 'Paris',
            ],
        ]);
        $section->questions()->attach($question->id, ['question_order' => 1]);

        return [$set, $section, $question];
    }
}
