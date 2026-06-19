<?php

namespace Modules\TelegramBot\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Gamification\Services\GamificationService;
use Modules\TelegramBot\Models\ReadingAttempt;
use Modules\TelegramBot\Models\ReadingPassage;
use Modules\TelegramBot\Models\ReadingPassageReview;

/**
 * Reading-passage review service.
 *
 * Owns the queue + grading logic for the reading-comprehension "review"
 * feature. The actual SM-2 algorithm lives in SpacedRepetitionService and is
 * reused for both vocabulary cards and reading passages: both schedule
 * tables share the same column layout (ease_factor, interval_days,
 * repetitions, next_review_at, last_grade), so grade() can be applied to
 * either via a thin adapter.
 *
 * One user submitting a passage creates N ReadingAttempt rows (one per
 * question) and exactly one ReadingPassageReview row (UPSERT). The review
 * row is what the next /reading-review or /review command will surface.
 */
class ReadingPassageService
{
    public function __construct(
        private readonly SpacedRepetitionService $srs,
        private readonly GamificationService $gamification,
    ) {
    }

    /**
     * Per-point reward for each correct answer inside a passage. Tuned
     * smaller than free-practice (10 points) because a passage covers
     * several questions and we don't want a single 3-question run to
     * dominate the daily XP leaderboard.
     */
    public const POINTS_PER_CORRECT = 4;

    public const XP_STREAK_BONUS = 5;

    /**
     * The reading-review "deck" for a user: up to $limit due passages,
     * eager-loaded with their questions.
     */
    public function dueQueue(User $user, int $limit = 10): Collection
    {
        return ReadingPassageReview::query()
            ->forUser($user->id)
            ->due()
            ->with(['passage.passageQuestions.question'])
            ->orderBy('next_review_at')
            ->limit($limit)
            ->get();
    }

    /**
     * How many cards are currently due for the user.
     */
    public function dueCount(User $user): int
    {
        return ReadingPassageReview::query()
            ->forUser($user->id)
            ->due()
            ->count();
    }

    /**
     * How many passages the user has on their "deck" at all (new + mature).
     */
    public function totalEnrolled(User $user): int
    {
        return ReadingPassageReview::query()
            ->forUser($user->id)
            ->count();
    }

    /**
     * How many of the user's enrolled passages are mature
     * (repetitions >= MATURE_REPETITIONS). Used by the stats endpoint
     * and by the topic-completion check in TelegramLearningService.
     */
    public function matureCount(User $user): int
    {
        return ReadingPassageReview::query()
            ->forUser($user->id)
            ->where('repetitions', '>=', ReadingPassageReview::MATURE_REPETITIONS)
            ->count();
    }

    /**
     * Pick the next passage the user should review. Priority:
     *   1. A passage that's currently due (next_review_at <= now)
     *   2. A passage the user has never attempted
     *   3. An enrolled passage that isn't yet mature (repetitions < 2)
     *   4. null — nothing to do
     *
     * Kept on the service rather than the command layer so the
     * "what's next" rule lives in one place and the Telegram flow
     * doesn't need to know about ReadingPassageReview directly.
     */
    public function pickNextForUser(User $user): ?ReadingPassage
    {
        $due = $this->dueQueue($user, 1)->first();
        if ($due && $due->passage) {
            return $due->passage;
        }

        $enrolledIds = ReadingPassageReview::query()
            ->forUser($user->id)
            ->pluck('reading_passage_id')
            ->all();

        // 2) A passage the user has never attempted.
        $never = ReadingPassage::query()
            ->active()
            ->whereNotIn('id', $enrolledIds ?: [0])
            ->orderBy('id')
            ->first();
        if ($never) {
            return $never;
        }

        // 3) An enrolled-but-not-yet-mature passage. We need BOTH
        // "is enrolled" AND "repetitions < MATURE". A single NOT IN
        // subquery against the mature reviews is the cleanest way.
        if ($enrolledIds === []) {
            return null;
        }

        $matureIds = ReadingPassageReview::query()
            ->forUser($user->id)
            ->whereIn('reading_passage_id', $enrolledIds)
            ->where('repetitions', '>=', ReadingPassageReview::MATURE_REPETITIONS)
            ->pluck('reading_passage_id')
            ->all();

        return ReadingPassage::query()
            ->active()
            ->whereIn('id', array_values(array_diff($enrolledIds, $matureIds)))
            ->orderBy('id')
            ->first();
    }

    /**
     * Library entries for the web /reading-review page.
     *
     * Returns every active passage the user can see, with the user's
     * per-passage review (if any) attached. The view uses this to render
     * "New", "Due", "Mature", and "Locked" badges.
     */
    public function library(User $user, ?int $topicId = null, ?string $difficulty = null): Collection
    {
        $query = ReadingPassage::query()
            ->active()
            ->with(['topic', 'passageQuestions.question']);

        if ($topicId) {
            $query->forTopic($topicId);
        }
        if ($difficulty) {
            $query->difficulty($difficulty);
        }

        $passages = $query->orderBy('id')->get();
        if ($passages->isEmpty()) {
            return $passages;
        }

        $reviews = ReadingPassageReview::query()
            ->forUser($user->id)
            ->whereIn('reading_passage_id', $passages->pluck('id'))
            ->get()
            ->keyBy('reading_passage_id');

        return $passages->each(function (ReadingPassage $passage) use ($reviews) {
            $review = $reviews->get($passage->id);
            $passage->setAttribute('user_review', $review);
            $passage->setAttribute('is_due', $review ? $review->next_review_at === null || $review->next_review_at->lte(now()) : false);
            $passage->setAttribute('is_mature', $review ? $review->isMature() : false);
        });
    }

    /**
     * One library entry, with questions + the user's review row.
     */
    public function findForUser(int $passageId, User $user): ?ReadingPassage
    {
        return ReadingPassage::query()
            ->active()
            ->with(['topic', 'passageQuestions.question'])
            ->where('id', $passageId)
            ->first()
            ?->setAttribute('user_review', $this->reviewFor($passageId, $user));
    }

    public function reviewFor(int $passageId, User $user): ?ReadingPassageReview
    {
        return ReadingPassageReview::query()
            ->forUser($user->id)
            ->where('reading_passage_id', $passageId)
            ->first();
    }

    /**
     * Grade a passage attempt and persist:
     *   - N ReadingAttempt rows (analytics)
     *   - 1 ReadingPassageReview row (UPSERT + SM-2 update)
     *   - XP for correct answers (GamificationService)
     *
     * $answers is keyed by question_id. $grade mirrors the SM-2 grade
     * (0=Again, 1=Hard, 2=Good, 3=Easy) and is applied to the schedule.
     * Telegram flow uses $grade; web flow infers it from the per-question
     * accuracy (e.g. >=80% correct -> GOOD, >=50% -> HARD, else AGAIN).
     */
    public function submitAttempt(
        User $user,
        ReadingPassage $passage,
        array $answers,
        ?int $grade = null,
        ?int $timeSpentMs = null,
        bool $applySchedule = true,
    ): array {
        $questions = $passage->passageQuestions->pluck('question')->filter()->values();

        if ($questions->isEmpty()) {
            return [
                'ok' => false,
                'reason' => 'passage_has_no_questions',
                'correct' => 0,
                'total' => 0,
                'points_earned' => 0,
            ];
        }

        $totalQuestions = $questions->count();
        $correctCount = 0;
        $attempts = [];

        DB::transaction(function () use ($questions, $user, $passage, $answers, $timeSpentMs, &$correctCount, &$attempts) {
            foreach ($questions as $question) {
                $studentAnswer = trim((string) ($answers[$question->id] ?? ''));
                $expected = trim((string) ($question->content['answer'] ?? ''));
                $isCorrect = $studentAnswer !== '' && strcasecmp($studentAnswer, $expected) === 0;
                if ($isCorrect) {
                    $correctCount++;
                }

                $attempt = ReadingAttempt::query()->create([
                    'user_id' => $user->id,
                    'reading_passage_id' => $passage->id,
                    'question_id' => $question->id,
                    'student_answer' => $studentAnswer,
                    'is_correct' => $isCorrect,
                    'points_earned' => $isCorrect ? self::POINTS_PER_CORRECT : 0,
                    'time_spent_ms' => $timeSpentMs,
                    'attempted_at' => Carbon::now(),
                ]);
                $attempts[] = $attempt;
            }
        });

        $pointsEarned = $correctCount * self::POINTS_PER_CORRECT;
        if ($correctCount > 0) {
            $this->gamification->awardPoints($user, $pointsEarned);
        }

        $schedule = ReadingPassageReview::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'reading_passage_id' => $passage->id,
            ],
            [
                'ease_factor' => 2.50,
                'interval_days' => 0,
                'repetitions' => 0,
                'next_review_at' => Carbon::now()->addDay(),
            ],
        );

        if ($applySchedule) {
            $grade ??= $this->deriveGrade($correctCount, $totalQuestions);
            $this->srs->grade($schedule, $grade);
        }

        return [
            'ok' => true,
            'correct' => $correctCount,
            'total' => $totalQuestions,
            'accuracy' => $totalQuestions > 0 ? $correctCount / $totalQuestions : 0.0,
            'points_earned' => $pointsEarned,
            'grade' => $grade,
            'next_review_at' => $schedule->fresh()->next_review_at?->toIso8601String(),
            'attempts' => $attempts,
        ];
    }

    /**
     * Map a (correct, total) pair to the SM-2 grade the user effectively
     * "self-assigned" by getting answers right. Used by the web flow
     * where the user only sees 4 grade buttons AFTER seeing the recap.
     */
    public function deriveGrade(int $correct, int $total): int
    {
        if ($total <= 0) {
            return ReadingPassageReview::GRADE_AGAIN;
        }
        $ratio = $correct / $total;
        return match (true) {
            $ratio >= 1.0 => ReadingPassageReview::GRADE_EASY,
            $ratio >= 0.75 => ReadingPassageReview::GRADE_GOOD,
            $ratio >= 0.5 => ReadingPassageReview::GRADE_HARD,
            default => ReadingPassageReview::GRADE_AGAIN,
        };
    }

    /**
     * Enrol a user in a passage (creates a fresh review row) without
     * grading. Used when the user clicks "Add to my deck" from the
     * library without having answered any questions yet.
     */
    public function enrol(User $user, ReadingPassage $passage): ReadingPassageReview
    {
        return ReadingPassageReview::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'reading_passage_id' => $passage->id,
            ],
            [
                'ease_factor' => 2.50,
                'interval_days' => 0,
                'repetitions' => 0,
                'next_review_at' => Carbon::now()->addDay(),
            ],
        );
    }

    /**
     * Stats payload for the dashboard widget.
     */
    public function stats(User $user): array
    {
        $now = Carbon::now();
        $today = $now->toDateString();

        $due = $this->dueCount($user);
        $total = $this->totalEnrolled($user);
        $mature = $this->matureCount($user);

        $todayAttempts = ReadingAttempt::query()
            ->where('user_id', $user->id)
            ->whereDate('attempted_at', $today)
            ->count();

        $todayCorrect = ReadingAttempt::query()
            ->where('user_id', $user->id)
            ->whereDate('attempted_at', $today)
            ->where('is_correct', true)
            ->count();

        return [
            'due_today' => $due,
            'reviewed_today' => $todayAttempts,
            'correct_today' => $todayCorrect,
            'accuracy_today' => $todayAttempts > 0 ? round($todayCorrect / $todayAttempts, 3) : 0.0,
            'total_passages' => $total,
            'mature_passages' => $mature,
            'streak' => $user->streak ?? 0,
        ];
    }

    /**
     * Count of mature passages for a (user, topic) pair — used by
     * TelegramLearningService::completeCurrentTopicIfEligible().
     */
    public function matureCountForTopic(User $user, int $topicId): int
    {
        return ReadingPassageReview::query()
            ->forUser($user->id)
            ->where('repetitions', '>=', ReadingPassageReview::MATURE_REPETITIONS)
            ->whereHas('passage', function ($q) use ($topicId) {
                $q->where('topic_id', $topicId);
            })
            ->count();
    }

    /**
     * Count of all passages attached to a topic — denominator of the
     * maturity ratio.
     */
    public function totalForTopic(int $topicId): int
    {
        return ReadingPassage::query()
            ->active()
            ->where('topic_id', $topicId)
            ->count();
    }
}
