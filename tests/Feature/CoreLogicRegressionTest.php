<?php

namespace Tests\Feature;

use App\Models\LessonRequest;
use App\Models\User;
use App\Services\LessonQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Classroom\Models\Classroom;
use Modules\Course\Models\Course;
use Tests\TestCase;

class CoreLogicRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_unsecured_telegram_webhook_is_not_routable(): void
    {
        $this->postJson('/api/telegram/webhook', [
            'callback_query' => ['data' => 'approve_user_1'],
        ])->assertNotFound();
    }

    public function test_course_quota_counts_courses_created_by_the_teacher(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
            'lesson_limit' => 2,
        ]);

        Course::query()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Teacher course',
            'description' => 'Owned by the teacher',
            'status' => 'active',
        ]);

        $result = app(LessonQuotaService::class)->check(
            $teacher,
            LessonRequest::TYPE_COURSE,
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame(1, $result['used']);
        $this->assertSame(2, $result['limit']);
    }

    public function test_student_search_can_find_an_enrolled_classroom(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $classroom = Classroom::query()->create([
            'teacher_id' => $teacher->id,
            'name' => 'Logic English',
            'invite_code' => 'LOGIC12345',
        ]);
        $classroom->students()->attach($student->id, ['role' => 'student']);

        $this->actingAs($student)
            ->getJson('/search?q=Logic')
            ->assertOk()
            ->assertJsonPath('groups.classrooms.0.id', $classroom->id);
    }
}
