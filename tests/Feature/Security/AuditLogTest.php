<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\LessonRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that AuditLogger::log() writes rows for sensitive
 * actions, and that the audit.admin middleware tags baseline rows
 * for every mutating request to /admin/* routes.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_user_writes_audit_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $target = User::factory()->create(['role' => 'student', 'status' => 'pending']);

        $this->actingAs($admin)->post("/admin/users/{$target->id}/approve")
            ->assertSessionHas('success');

        $log = AuditLog::where('action', 'user.approved')
            ->where('target_id', (string) $target->id)
            ->first();

        $this->assertNotNull($log, 'audit row should be written');
        $this->assertEquals($admin->id, $log->actor_id);
        $this->assertEquals('web', $log->metadata['via']);
    }

    public function test_lesson_request_submission_is_logged(): void
    {
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($user)->post('/lesson-requests', [
            'lesson_type' => LessonRequest::TYPE_COURSE,
            'requested_extra' => 5,
            'reason' => 'for testing',
        ]);

        $log = AuditLog::where('action', 'lesson_request.submitted')->first();

        $this->assertNotNull($log);
        $this->assertEquals(5, $log->metadata['requested_extra']);
    }

    public function test_admin_post_route_records_baseline_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/feedback/1/note', [
            'note' => 'follow up',
        ]);

        // The middleware writes an admin.route.post row whenever a
        // mutating request hits /admin/*. It should exist regardless
        // of whether the inner controller succeeded.
        $log = AuditLog::where('action', 'admin.route.post')->first();

        $this->assertNotNull($log, 'audit.admin middleware must log mutating admin requests');
        $this->assertEquals('admin/feedback/1/note', $log->metadata['path']);
    }
}