<?php

namespace Modules\TelegramBot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Models\ReadingPassage;
use Modules\TelegramBot\Services\ReadingPassageService;

/**
 * Web UI for the reading-comprehension "review" feature.
 *
 * Mirrors the FlashcardController pattern:
 *   - GET  /reading-review                -> library (browse all passages)
 *   - GET  /reading-review/session        -> review session (queue + grade)
 *   - POST /reading-review/{passage}/grade -> grade a passage attempt (JSON)
 *   - GET  /reading-review/stats          -> JSON stats for the dashboard widget
 *
 * The same ReadingPassageService is also called from the Telegram flow
 * (TelegramBotCommandService), so the web and bot experiences share a
 * single queue and the same SM-2 state.
 */
class ReadingPassageReviewController extends Controller
{
    public function __construct(private readonly ReadingPassageService $service)
    {
    }

    /**
     * GET /reading-review — library page.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'topic_id' => ['nullable', 'integer'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
        ]);

        $passages = $this->service->library(
            $user,
            isset($validated['topic_id']) ? (int) $validated['topic_id'] : null,
            $validated['difficulty'] ?? null,
        );

        $stats = $this->service->stats($user);

        return view('telegrambot::reading-review.library', [
            'passages' => $passages,
            'stats' => $stats,
            'filters' => [
                'topic_id' => $validated['topic_id'] ?? null,
                'difficulty' => $validated['difficulty'] ?? null,
            ],
        ]);
    }

    /**
     * GET /reading-review/session — show the next due passage (or the
     * one requested via ?passage=).
     */
    public function session(Request $request)
    {
        $user = $request->user();

        $requestedId = $request->query('passage');
        $passage = null;

        if ($requestedId) {
            $passage = $this->service->findForUser((int) $requestedId, $user);
        }

        if (! $passage) {
            $queue = $this->service->dueQueue($user, 1);
            $passage = $queue->first()?->passage
                ? $this->service->findForUser($queue->first()->passage->id, $user)
                : null;
        }

        $stats = $this->service->stats($user);

        return view('telegrambot::reading-review.session', [
            'passage' => $passage,
            'stats' => $stats,
        ]);
    }

    /**
     * POST /reading-review/{passage}/grade — submit answers and a self-grade.
     */
    public function grade(Request $request, ReadingPassage $passage): JsonResponse
    {
        $user = $request->user();

        if (! $passage->is_active) {
            return response()->json(['ok' => false, 'reason' => 'inactive'], 404);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:2000'],
            'grade' => ['nullable', 'integer', 'between:0,3'],
            'time_spent_ms' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $result = $this->service->submitAttempt(
                $user,
                $passage,
                $validated['answers'],
                isset($validated['grade']) ? (int) $validated['grade'] : null,
                isset($validated['time_spent_ms']) ? (int) $validated['time_spent_ms'] : null,
            );
        } catch (\Throwable $e) {
            Log::error('[ReadingPassageReview] grade failed', [
                'user_id' => $user->id,
                'passage_id' => $passage->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'ok' => false,
                'reason' => 'server_error',
                'message' => 'Could not grade attempt, please try again.',
            ], 500);
        }

        $stats = $this->service->stats($user);
        $result['stats'] = $stats;
        $result['due_remaining'] = $stats['due_today'];

        return response()->json($result);
    }

    /**
     * GET /reading-review/stats — JSON stats for the dashboard widget.
     */
    public function stats(Request $request): JsonResponse
    {
        return response()->json($this->service->stats($request->user()));
    }

    /**
     * POST /reading-review/{passage}/enrol — add a passage to the user's
     * deck without grading. Used by the "Add to my deck" button on the
     * library page.
     */
    public function enrol(Request $request, ReadingPassage $passage): JsonResponse
    {
        $user = $request->user();

        if (! $passage->is_active) {
            return response()->json(['ok' => false, 'reason' => 'inactive'], 404);
        }

        $review = $this->service->enrol($user, $passage);

        return response()->json([
            'ok' => true,
            'review_id' => $review->id,
            'next_review_at' => $review->next_review_at?->toIso8601String(),
        ]);
    }
}
