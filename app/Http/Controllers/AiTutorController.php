<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AiTutorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AiTutorController extends Controller
{
    public function __construct(protected AiTutorService $tutor)
    {
    }

    /**
     * POST /ai/tutor — free-form question.
     * Rate-limited via the `ai` named limiter (20/min/user).
     */
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        $answer = $this->tutor->ask($request->user(), $request->validated('question'));

        return response()->json([
            'answer' => $answer,
        ]);
    }

    /**
     * POST /ai/tutor/explain — explain a wrong answer.
     */
    public function explain(Request $request): JsonResponse
    {
        $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'user_answer' => ['required', 'string', 'max:500'],
            'correct_answer' => ['required', 'string', 'max:500'],
        ]);

        $explanation = $this->tutor->explain(
            $request->user(),
            $request->validated('question'),
            $request->validated('user_answer'),
            $request->validated('correct_answer'),
        );

        return response()->json(['explanation' => $explanation]);
    }

    /**
     * POST /ai/tutor/suggest — recommended next lesson/activity.
     */
    public function suggest(Request $request): JsonResponse
    {
        $request->validate([
            'recent_mistakes' => ['array'],
            'recent_mistakes.*.skill' => ['string'],
            'recent_mistakes.*.topic' => ['string'],
            'recent_mistakes.*.wrong_count' => ['integer'],
        ]);

        $suggestion = $this->tutor->suggestNext(
            $request->user(),
            $request->input('recent_mistakes', []),
        );

        return response()->json(['suggestion' => $suggestion]);
    }

    /**
     * POST /ai/tutor/clear — wipe conversation history.
     */
    public function clear(Request $request): JsonResponse
    {
        $this->tutor->clearHistory($request->user());

        return response()->json(['cleared' => true]);
    }
}