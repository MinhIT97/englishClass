<?php

namespace Modules\Auth\Services;

use Modules\Auth\Repositories\UserRepositoryInterface;
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
     */
    public function register(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'student';
        $data['status'] = 'pending';
        $data['target_band'] = $data['target_band'] ?? null;

        return $this->userRepository->create($data);
    }

    /**
     * Login user and return token.
     */
    public function login(array $credentials)
    {
        $user = $this->userRepository->findByEmail($credentials['email']);

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
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
