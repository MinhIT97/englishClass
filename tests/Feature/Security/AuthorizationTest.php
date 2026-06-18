<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the role-based authorization checks added during the
 * 2026-06 security hardening pass.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_create_course_via_form_request(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => 'active',
        ]);

        $response = $this->actingAs($student)->postJson('/courses', [
            'title' => 'Test Course',
        ]);

        $response->assertStatus(403);
    }

    public function test_teacher_can_create_course(): void
    {
        $teacher = User::factory()->create([
            'role' => UserRole::Teacher->value,
            'status' => 'active',
        ]);

        $response = $this->actingAs($teacher)->postJson('/courses', [
            'title' => 'Test Course',
        ]);

        $response->assertStatus(201);
    }

    public function test_inactive_teacher_cannot_create_course(): void
    {
        $teacher = User::factory()->create([
            'role' => UserRole::Teacher->value,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($teacher)->postJson('/courses', [
            'title' => 'Test Course',
        ]);

        $response->assertStatus(403);
    }

    public function test_non_admin_cannot_delete_course(): void
    {
        $teacher = User::factory()->create([
            'role' => UserRole::Teacher->value,
            'status' => 'active',
        ]);

        // First create a course as admin to get an ID we can try to delete.
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'status' => 'active']);
        $courseId = $this->actingAs($admin)->postJson('/courses', ['title' => 'Test'])->json('data.id');

        $this->actingAs($teacher)->deleteJson("/courses/{$courseId}")
            ->assertStatus(403);
    }

    public function test_role_middleware_rejects_unauthenticated(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/courses', ['title' => 'Test']);

        $response->assertStatus(401);
    }
}