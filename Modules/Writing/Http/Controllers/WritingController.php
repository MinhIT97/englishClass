<?php

namespace Modules\Writing\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Writing\Http\Requests\SubmitWritingRequest;
use Modules\Writing\Services\WritingGraderService;
use Modules\Writing\Models\WritingAttempt;

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
        $attempts = WritingAttempt::query()->forUser(auth()->id())->latest()->paginate(10);
        return view('writing::index', compact('attempts'));
    }

    /**
     * Submit essay for grading.
     */
    public function submit(SubmitWritingRequest $request)
    {
        $attempt = $this->graderService->gradeEssay(
            auth()->id(),
            $request->string('essay_content')->toString(),
            $request->string('task_type')->toString()
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
        $attempt = WritingAttempt::query()->forUser(auth()->id())->findOrFail($id);
        return view('writing::show', compact('attempt'));
    }
}
