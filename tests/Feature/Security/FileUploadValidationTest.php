<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Classroom\Models\Classroom;
use Tests\TestCase;

/**
 * Ensures StoreClassroomPostRequest rejects files with disallowed
 * MIME types — without this, a teacher could upload a .php file
 * that, depending on storage disk configuration, might be served
 * as PHP by the web server.
 */
class FileUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_php_file_upload_is_rejected(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $classroom = Classroom::create([
            'name' => 'Test Room',
            'description' => null,
            'teacher_id' => $teacher->id,
            'invite_code' => 'ABCDEFGHIJ',
        ]);

        $payload = UploadedFile::fake()->create('shell.php', 10);

        $response = $this->actingAs($teacher)->post(
            "/classroom/{$classroom->id}/post",
            ['content' => 'evil', 'type' => 'text', 'attachment' => $payload],
        );

        $response->assertSessionHasErrors('attachment');
    }

    public function test_pdf_file_upload_is_accepted(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $classroom = Classroom::create([
            'name' => 'Test Room',
            'description' => null,
            'teacher_id' => $teacher->id,
            'invite_code' => 'PDFTESTABCD',
        ]);

        $payload = UploadedFile::fake()->create('homework.pdf', 100, 'application/pdf');

        $response = $this->actingAs($teacher)->post(
            "/classroom/{$classroom->id}/post",
            ['content' => 'homework', 'type' => 'text', 'attachment' => $payload],
        );

        // Either a redirect back with success or a 5xx from the
        // service; the key assertion is that NO validation error
        // on `attachment` was emitted.
        $response->assertSessionDoesntHaveErrors('attachment');
    }
}