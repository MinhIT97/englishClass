<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PronunciationShadowingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_grade_audio(): void
    {
        $response = $this->postJson('/speaking/grade-audio', [
            'reference_text' => 'Hello world',
        ]);

        $response->assertStatus(401);
    }

    public function test_validation_rejects_missing_parameters(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => 'active',
        ]);

        $response = $this->actingAs($student)->postJson('/speaking/grade-audio', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio', 'reference_text']);
    }

    public function test_valid_audio_grading_returns_mock_feedback(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => 'active',
        ]);

        $fakeAudio = UploadedFile::fake()->create('recording.webm', 100, 'audio/webm');

        $response = $this->actingAs($student)->postJson('/speaking/grade-audio', [
            'audio' => $fakeAudio,
            'reference_text' => 'Individuals can make a difference.',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['feedback'])
            ->assertJsonFragment([
                'feedback' => '<p><strong>[MOCK MODE]</strong> Phát âm của bạn rất tốt! Bạn đã phát âm chính xác từ: "Individuals can make a difference.". Nhịp điệu và ngữ điệu tự nhiên, tiếp tục phát huy nhé!</p>'
            ]);
    }
}
