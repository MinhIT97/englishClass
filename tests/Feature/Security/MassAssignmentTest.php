<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirms that AuthService::register ignores client-supplied role
 * and status. A naive refactor to User::create($request->all())
 * would let an attacker escalate to admin by POSTing role=admin.
 */
class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_cannot_set_role_to_admin(): void
    {
        $response = $this->post('/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'Pass1234!@',           // meets Password::min(8)->mixedCase()->numbers()->symbols()
            'password_confirmation' => 'Pass1234!@',
            'role' => 'admin',
            'status' => 'active',
            'is_unlimited' => true,
        ]);

        $response->assertSessionHas('success');

        $user = User::where('email', 'mallory@example.com')->first();

        $this->assertNotNull($user);
        $this->assertEquals('student', $user->role, 'role must be forced to student');
        $this->assertEquals('pending', $user->status, 'status must be forced to pending');
        $this->assertFalse((bool) $user->is_unlimited, 'is_unlimited flag must remain false');
    }
}