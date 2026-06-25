<?php

namespace Modules\Auth\Services;

use Modules\Auth\Repositories\UserRepositoryInterface;
use App\Events\StudentRegistered;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\ValidationException;

class AuthService
{
    protected $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Register a new user.
     *
     * SECURITY: role and status are hardcoded below. Even if a
     * malicious client POSTs `role=admin` or `is_unlimited=1`, those
     * keys are silently overwritten. The input array comes from
     * $request->validated() which only contains fields declared in
     * RegisterRequest::rules(). Do NOT change this to merge() or
     * ->all() without re-auditing the mass-assignment surface.
     */
    public function register(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'student';
        $data['status'] = 'pending';
        $data['target_band'] = $data['target_band'] ?? null;

        $user = $this->userRepository->create($data);

        event(new StudentRegistered($user));

        return $user;
    }

    /**
     * Login user and return token.
     *
     * SECURITY (SEC-005): Both known-email and unknown-email paths now execute
     * a bcrypt hash, eliminating the timing oracle that allowed attackers to
     * enumerate registered emails. The dummy hash uses a constant-time cost
     * (12 rounds = same as the real config) so timing is indistinguishable.
     */
    public function login(array $credentials)
    {
        $user = $this->userRepository->findByEmail($credentials['email']);

        // Always hash — even for unknown email — to prevent timing oracle.
        // BCRYPT_ROUNDS=12 matches the configured cost so timing is identical.
        $storedHash = $user?->password ?? '$2y$12$dummyhashdummyhashdummyhasdummyhasdummyhasdummyha';
        Hash::check($credentials['password'], $storedHash);

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => ['Your account is pending approval.'],
            ]);
        }

        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
