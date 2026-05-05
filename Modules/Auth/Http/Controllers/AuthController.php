<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Resources\UserResource;
use Modules\Auth\Services\AuthService;
use App\Services\DashboardService;
use App\Services\PerformanceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Gamification\Services\GamificationService;

class AuthController extends Controller
{
    protected $authService;
    protected DashboardService $dashboardService;
    protected PerformanceAnalyticsService $performanceAnalyticsService;
    protected GamificationService $gamificationService;

    public function __construct(
        AuthService $authService,
        DashboardService $dashboardService,
        PerformanceAnalyticsService $performanceAnalyticsService,
        GamificationService $gamificationService
    )
    {
        $this->authService = $authService;
        $this->dashboardService = $dashboardService;
        $this->performanceAnalyticsService = $performanceAnalyticsService;
        $this->gamificationService = $gamificationService;
    }

    /* --- WEB METHODS --- */

    public function showLogin()
    {
        return view('auth::login');
    }

    public function adminDashboard()
    {
        $stats = $this->dashboardService->adminStats();

        return view('auth::admin.dashboard', compact('stats'));
    }

    public function studentDashboard()
    {
        $user = auth()->user();

        $levelData = $this->gamificationService->getLevelData($user);

        $performance = $this->performanceAnalyticsService->studentPerformance($user->id);

        return view('auth::student.dashboard', [
            'levelData' => $levelData,
            'accuracy' => $performance['accuracy'],
            'totalAnswers' => $performance['total_answers'],
            'correctAnswers' => $performance['correct_answers'],
            'incorrectAnswers' => $performance['incorrect_answers'],
            'skillStats' => $performance['skill_stats'],
            'skillAttempts' => $performance['skill_attempts'],
            'skillCorrectCounts' => $performance['skill_correct_counts'],
        ]);
    }

    public function showRegister()
    {
        return view('auth::register');
    }

    /**
     * Handle Web Login.
     */
    public function webLogin(LoginRequest $request)
    {
        try {
            $data = $this->authService->login($request->validated());

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
    public function webRegister(RegisterRequest $request)
    {
        $this->authService->register($request->validated());

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
