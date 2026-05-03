<?php

namespace Modules\IeltsSet\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Modules\IeltsSet\Models\IeltsSetSection;
use Modules\IeltsSet\Models\IeltsSet;
use Modules\IeltsSet\Models\IeltsSetAttempt;
use Modules\IeltsSet\Models\IeltsSetAttemptAnswer;
use Modules\Practice\Services\PracticeSessionService;

class IeltsSetController extends Controller
{
    public function index(Request $request)
    {
        $sets = IeltsSet::query()
            ->published()
            ->with('sections')
            ->withCount('sections')
            ->orderByDesc('id')
            ->get()
            ->map(function (IeltsSet $set) use ($request) {
                $set->latest_attempt = $set->latestAttemptFor($request->user());
                return $set;
            });

        return view('ieltset::index', compact('sets'));
    }

    public function show(Request $request, IeltsSet $set)
    {
        abort_unless($set->is_published, 404);

        $set->load(['sections.questions']);
        $latestAttempt = $set->latestAttemptFor($request->user());
        $attemptHistory = $set->attempts()
            ->where('user_id', $request->user()->id)
            ->latest('started_at')
            ->take(5)
            ->get();

        return view('ieltset::show', compact('set', 'latestAttempt', 'attemptHistory'));
    }

    public function section(Request $request, IeltsSet $set, IeltsSetSection $section)
    {
        abort_unless($section->ielts_set_id === $set->id, 404);

        if ($section->skill === 'speaking') {
            return redirect()
                ->route('student.speaking.index')
                ->with('info', 'Speaking sections are completed in the dedicated AI speaking simulator.');
        }

        $attempt = $this->getOrCreateCurrentAttempt($set, $request->user()->id);
        $section->load('questions');
        $answers = $attempt->answers()
            ->where('ielts_set_section_id', $section->id)
            ->get()
            ->keyBy('question_id');

        $meta = $attempt->meta ?? [];
        $sectionTimers = Arr::get($meta, 'section_timers', []);

        if (!isset($sectionTimers[$section->id]['started_at'])) {
            $sectionTimers[$section->id] = [
                'started_at' => now()->toIso8601String(),
                'active_seconds' => 0,
                'completed_at' => null,
            ];
            $meta['section_timers'] = $sectionTimers;
            $attempt->update(['meta' => $meta]);
        }

        return view('ieltset::section', [
            'set' => $set,
            'section' => $section,
            'attempt' => $attempt,
            'savedAnswers' => $answers,
            'sectionTimer' => $sectionTimers[$section->id] ?? null,
        ]);
    }

    public function updateSectionTime(Request $request, IeltsSet $set, IeltsSetSection $section)
    {
        abort_unless($section->ielts_set_id === $set->id, 404);

        $validated = $request->validate([
            'seconds' => ['required', 'integer', 'min:0', 'max:3600'],
        ]);

        $attempt = $this->getOrCreateCurrentAttempt($set, $request->user()->id);
        $meta = $attempt->meta ?? [];
        $sectionTimers = Arr::get($meta, 'section_timers', []);
        $timer = $sectionTimers[$section->id] ?? [
            'started_at' => now()->toIso8601String(),
            'active_seconds' => 0,
            'completed_at' => null,
        ];

        $timer['active_seconds'] = (int) ($timer['active_seconds'] ?? 0) + (int) $validated['seconds'];
        $timer['last_synced_at'] = now()->toIso8601String();
        $sectionTimers[$section->id] = $timer;
        $meta['section_timers'] = $sectionTimers;

        $attempt->update(['meta' => $meta]);

        return response()->json([
            'ok' => true,
            'active_seconds' => $timer['active_seconds'],
        ]);
    }

    public function submitSection(
        Request $request,
        IeltsSet $set,
        IeltsSetSection $section,
        PracticeSessionService $practiceSessionService
    ) {
        abort_unless($section->ielts_set_id === $set->id, 404);

        if ($section->skill === 'speaking') {
            return redirect()->route('student.speaking.index');
        }

        $section->load('questions');
        $attempt = $this->getOrCreateCurrentAttempt($set, $request->user()->id);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string'],
            'active_seconds_delta' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $answers = $validated['answers'] ?? [];

        foreach ($section->questions as $question) {
            $answer = trim((string) ($answers[$question->id] ?? ''));

            if ($answer === '') {
                $result = [
                    'skipped' => true,
                    'is_correct' => false,
                    'feedback' => 'No answer submitted for this prompt.',
                    'correct_answer' => $question->content['answer'] ?? 'No reference answer available.',
                    'points_earned' => 0,
                ];
            } else {
                $result = $practiceSessionService->submitAnswer(
                    $request->user(),
                    $question->id,
                    $answer
                );
            }

            IeltsSetAttemptAnswer::updateOrCreate(
                [
                    'ielts_set_attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                ],
                [
                    'ielts_set_section_id' => $section->id,
                    'answer_text' => $answer !== '' ? $answer : null,
                    'is_correct' => (bool) ($result['is_correct'] ?? false),
                    'points_earned' => (int) ($result['points_earned'] ?? 0),
                    'correct_answer' => (string) ($result['correct_answer'] ?? ''),
                    'feedback' => (string) ($result['feedback'] ?? ''),
                    'answered_at' => now(),
                ]
            );
        }

        $meta = $attempt->meta ?? [];
        $sectionTimers = Arr::get($meta, 'section_timers', []);
        $activeSecondsDelta = (int) ($validated['active_seconds_delta'] ?? 0);
        $currentTimer = $sectionTimers[$section->id] ?? [
            'started_at' => now()->toIso8601String(),
            'active_seconds' => 0,
        ];
        $currentTimer['active_seconds'] = (int) ($currentTimer['active_seconds'] ?? 0) + $activeSecondsDelta;
        $sectionTimers[$section->id] = array_merge(
            $currentTimer,
            ['completed_at' => now()->toIso8601String()]
        );

        $meta['section_timers'] = $sectionTimers;
        $meta['last_section_id'] = $section->id;
        $meta['last_section_skill'] = $section->skill;
        $meta['last_submitted_at'] = now()->toIso8601String();

        $scorePercent = $this->calculateScorePercent($attempt);

        $attemptStatus = $this->resolveAttemptStatus($set, $attempt);

        $attempt->update([
            'status' => $attemptStatus,
            'submitted_at' => $attemptStatus === 'completed' ? now() : null,
            'score_percent' => $scorePercent,
            'meta' => $meta,
        ]);

        return redirect()
            ->route('student.sets.section', [$set, $section])
            ->with('success', 'Section submitted successfully. Review your feedback below.');
    }

    public function start(Request $request, IeltsSet $set)
    {
        abort_unless($set->is_published, 404);

        $this->getOrCreateCurrentAttempt($set, $request->user()->id);

        return redirect()
            ->route('student.sets.show', $set)
            ->with('info', 'Set session created. You can now work through each section in order.');
    }

    private function getOrCreateCurrentAttempt(IeltsSet $set, int $userId): IeltsSetAttempt
    {
        $attempt = $set->currentAttemptFor($userId);

        if ($attempt) {
            return $attempt;
        }

        return IeltsSetAttempt::create([
            'ielts_set_id' => $set->id,
            'user_id' => $userId,
            'status' => 'in_progress',
            'started_at' => now(),
            'meta' => [
                'set_title' => $set->title,
                'section_count' => $set->sections()->count(),
                'section_timers' => [],
            ],
        ]);
    }

    private function calculateScorePercent(IeltsSetAttempt $attempt): float
    {
        $answered = $attempt->answers()->count();

        if ($answered === 0) {
            return 0;
        }

        $correct = $attempt->answers()->where('is_correct', true)->count();

        return round(($correct / $answered) * 100, 2);
    }

    private function resolveAttemptStatus(IeltsSet $set, IeltsSetAttempt $attempt): string
    {
        $hasSpeakingSection = $set->sections()->where('skill', 'speaking')->exists();

        if ($hasSpeakingSection) {
            return 'in_progress';
        }

        $requiredQuestionCount = $set->sections()
            ->where('skill', '!=', 'speaking')
            ->withCount('questions')
            ->get()
            ->sum('questions_count');

        $answeredQuestionCount = $attempt->answers()->count();

        return $requiredQuestionCount > 0 && $answeredQuestionCount >= $requiredQuestionCount
            ? 'completed'
            : 'in_progress';
    }
}
