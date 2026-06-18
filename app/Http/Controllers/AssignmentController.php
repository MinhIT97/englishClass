<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Assignment + grading workflow. Assignments live on a classroom
 * and have many submissions. Auto-grade MC questions server-side;
 * let the teacher provide manual feedback on essay / speaking
 * submissions.
 */
class AssignmentController extends Controller
{
    public function index(Request $request, Classroom $classroom): View
    {
        $this->authorizeTeacher($request, $classroom);

        $assignments = $classroom->assignments()
            ->withCount('submissions')
            ->orderByDesc('due_at')
            ->get();

        return view('classroom.assignments.index', [
            'classroom' => $classroom,
            'assignments' => $assignments,
        ]);
    }

    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeTeacher($request, $classroom);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'rubric' => ['nullable', 'array'],
        ]);

        $classroom->assignments()->create($data);

        return back()->with('success', 'Đã tạo bài tập.');
    }

    public function submit(Request $request, Classroom $classroom, $assignment): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $sub = $classroom->assignments()->findOrFail($assignment)
            ->submissions()->create([
                'user_id' => $request->user()->id,
                'body' => $data['body'],
                'submitted_at' => Carbon::now(),
            ]);

        return back()->with('success', 'Đã nộp bài.');
    }

    public function grade(Request $request, $submission): RedirectResponse
    {
        $sub = \App\Models\AssignmentSubmission::findOrFail($submission);
        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string'],
        ]);

        $sub->update([
            'score' => $data['score'],
            'feedback' => $data['feedback'] ?? null,
            'graded_by' => $request->user()->id,
            'graded_at' => Carbon::now(),
        ]);

        return back()->with('success', 'Đã chấm điểm.');
    }

    private function authorizeTeacher(Request $request, Classroom $classroom): void
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin() || $classroom->teacher_id === $user->id,
            403,
        );
    }
}