<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validates that throttle middleware caps login, register, and AI
 * endpoints as configured in AppServiceProvider.
 *
 * Each test asserts the HTTP 429 response that Laravel's
 * ThrottleRequests middleware emits once the per-minute/per-hour
 * window is exhausted.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_throttles_after_five_attempts(): void
    {
        // Six failed logins should produce 5 regular responses and
        // one 429.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_register_throttles_after_three_attempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/register', [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        $response = $this->post('/register', [
            'name' => 'User 4',
            'email' => 'user4@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429);
    }

    public function test_ai_chat_is_rate_limited_per_user(): void
    {
        $user = User::factory()->create();

        // 20 requests succeed (the configured limit), the 21st is 429.
        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)->post('/ai/chat', ['message' => 'hi']);
        }

        $response = $this->actingAs($user)->post('/ai/chat', ['message' => 'hi']);
        $response->assertStatus(429);
    }
}