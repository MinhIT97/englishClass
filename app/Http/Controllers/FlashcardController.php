<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\TelegramBot\Models\ReviewSchedule;
use Modules\TelegramBot\Services\SpacedRepetitionService;

/**
 * Web UI for the spaced-repetition flashcard deck.
 *
 * Renders a daily review queue (cards whose next_review_at <= today)
 * and accepts grade submissions to update the schedule. The same
 * service is used by the Telegram bot so the SRS state stays in
 * sync regardless of which surface the user studies from.
 */
class FlashcardController extends Controller
{
    public function __construct(protected SpacedRepetitionService $srs)
    {
    }

    /**
     * GET /flashcards — render the review session page.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $due = $this->dueCards($user);

        return view('flashcards.index', [
            'cards' => $due,
            'count' => $due->count(),
            'dailyGoal' => 20,
        ]);
    }

    /**
     * POST /flashcards/{reviewSchedule}/grade — submit a grade.
     */
    public function grade(Request $request, ReviewSchedule $reviewSchedule): JsonResponse
    {
        $request->validate([
            'grade' => ['required', 'integer', 'between:0,3'],
        ]);

        // Ensure the schedule belongs to the authenticated user.
        abort_unless($reviewSchedule->user_id === $request->user()->id, 403);

        $this->srs->grade($reviewSchedule, (int) $request->validated('grade'));

        $remaining = $this->dueCards($request->user())->count();

        return response()->json([
            'graded' => true,
            'next_review' => $reviewSchedule->fresh()->next_review_at?->toIso8601String(),
            'remaining' => $remaining,
        ]);
    }

    /**
     * GET /flashcards/stats — review statistics for the dashboard widget.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();
        $today = $now->toDateString();

        $stats = [
            'due_today' => ReviewSchedule::query()
                ->where('user_id', $user->id)
                ->where('next_review_at', '<=', $now)
                ->count(),
            'reviewed_today' => ReviewSchedule::query()
                ->where('user_id', $user->id)
                ->whereDate('last_reviewed_at', $today)
                ->count(),
            'streak' => $user->streak ?? 0,
            'total_cards' => ReviewSchedule::query()
                ->where('user_id', $user->id)
                ->count(),
        ];

        return response()->json($stats);
    }

    protected function dueCards(User $user)
    {
        return ReviewSchedule::query()
            ->where('user_id', $user->id)
            ->where('next_review_at', '<=', Carbon::now())
            ->with('vocabularyEntry')
            ->orderBy('next_review_at')
            ->limit(50)
            ->get()
            ->map(function (ReviewSchedule $s) {
                $entry = $s->vocabularyEntry;
                return (object) [
                    'schedule_id' => $s->id,
                    'word' => $entry?->word ?? '(unknown)',
                    'ipa' => $entry?->ipa,
                    'pos' => $entry?->pos,
                    'meaning_vi' => $entry?->meaning_vi,
                    'meaning_en' => $entry?->meaning_en,
                    'example_en' => $entry?->example_en,
                    'example_vi' => $entry?->example_vi,
                ];
            });
    }
}