<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Resources\UserResource;
use Modules\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /* --- WEB METHODS --- */

    public function showLogin()
    {
        return view('auth::login');
    }

    public function adminDashboard()
    {
        // Simple stats for now
        $stats = [
            'total_students' => \App\Models\User::where('role', 'student')->count(),
            'pending_approvals' => \App\Models\User::where('role', 'student')->where('status', 'pending')->count(),
            'active_students' => \App\Models\User::where('role', 'student')->where('status', 'active')->count(),
        ];

        return view('auth::admin.dashboard', compact('stats'));
    }

    public function studentDashboard()
    {
        $user = auth()->user();
        
        // Level Data
        $gamified = app(\Modules\Gamification\Services\GamificationService::class);
        $levelData = $gamified->getLevelData($user);
        
        // Performance Stats
        $answers = \Modules\Practice\Models\UserAnswer::where('user_id', $user->id)->get();
        $totalAnswers = $answers->count();
        $correctAnswers = $answers->where('is_correct', true)->count();
        $accuracy = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100) : 0;
        
        // Skill Breakdown for Chart
        $skills = ['reading', 'listening', 'writing', 'speaking'];
        $skillStats = [];
        foreach ($skills as $skill) {
            $skillAnswers = \Modules\Practice\Models\UserAnswer::where('user_id', $user->id)
                ->whereHas('question', function($q) use ($skill) {
                    $q->where('skill', $skill);
                })->get();
            
            $skillTotal = $skillAnswers->count();
            $skillCorrect = $skillAnswers->where('is_correct', true)->count();
            $skillStats[$skill] = $skillTotal > 0 ? round(($skillCorrect / $skillTotal) * 100) : 0;
        }

        return view('auth::student.dashboard', compact('levelData', 'accuracy', 'skillStats'));
    }

    public function showRegister()
    {
        return view('auth::register');
    }

    /**
     * Handle Web Login.
     */
    public function webLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            $data = $this->authService->login($credentials);
            
            // Web Session Login
            Auth::login($data['user'], $request->boolean('remember'));

            if ($data['user']->role === 'admin') {
                return redirect('/admin/dashboard');
            }

            return redirect('/student/dashboard');

        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    /**
     * Handle Web Register.
     */
    public function webRegister(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'target_band' => 'nullable|string'
        ]);

        $user = $this->authService->register($data);

        // Notify user about pending status
        return redirect()->route('login')->with('success', 'Registration successful! Please wait for admin approval.');
    }

    /**
     * Handle Web Logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /* --- API METHODS --- */

    /**
     * Register a new user via API.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return response()->json([
            'message' => 'User registered successfully. Please wait for admin approval.',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Login user via API.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $this->authService->login($request->validated());

        return response()->json([
            'message' => 'Login successful.',
            'user' => new UserResource($data['user']),
            'access_token' => $data['token'],
            'token_type' => 'bearer',
        ]);
    }
}
