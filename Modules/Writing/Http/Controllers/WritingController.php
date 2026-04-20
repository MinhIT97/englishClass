<?php

namespace Modules\Writing\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Writing\Services\WritingGraderService;
use Modules\Writing\Models\WritingAttempt;
use Illuminate\Http\Request;

class WritingController extends Controller
{
    protected $graderService;

    public function __construct(WritingGraderService $graderService)
    {
        $this->graderService = $graderService;
    }

    /**
     * Show writing submission form.
     */
    public function index()
    {
        $attempts = WritingAttempt::where('user_id', auth()->id())->latest()->paginate(10);
        return view('writing::index', compact('attempts'));
    }

    /**
     * Submit essay for grading.
     */
    public function submit(Request $request)
    {
        $request->validate([
            'essay_content' => 'required|string|min:50',
            'task_type' => 'required|in:task_1,task_2'
        ]);

        $attempt = $this->graderService->gradeEssay(
            auth()->id(),
            $request->get('essay_content'),
            $request->get('task_type')
        );

        if (!$attempt) {
            return back()->with('error', 'AI Grading system is currently busy. Please try again later.')->withInput();
        }

        return redirect()->route('student.writing.show', $attempt->id)->with('success', 'Essay graded successfully!');
    }

    /**
     * Show results of a writing attempt.
     */
    public function show($id)
    {
        $attempt = WritingAttempt::where('user_id', auth()->id())->findOrFail($id);
        return view('writing::show', compact('attempt'));
    }
}
