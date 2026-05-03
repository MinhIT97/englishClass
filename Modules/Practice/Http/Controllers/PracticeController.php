<?php

namespace Modules\Practice\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Practice\Http\Requests\SubmitPracticeAnswerRequest;
use Modules\Practice\Http\Requests\SubmitPracticeSpeakingRequest;
use Modules\Practice\Services\PracticeSessionService;

class PracticeController extends Controller
{
    public function __construct(private readonly PracticeSessionService $practiceSessionService)
    {
    }

    public function index()
    {
        return view('practice::index');
    }

    public function showDrill(string $skill)
    {
        if ($skill === 'writing') {
            return redirect()->route('student.writing.index');
        }

        if ($skill === 'speaking') {
            return redirect()->route('student.speaking.index');
        }

        $question = $this->practiceSessionService->loadDrill($skill);

        if (!$question) {
            return redirect()->route('student.practice.index')->with('error', "No questions available for {$skill} yet.");
        }

        return view('practice::drill', compact('question', 'skill'));
    }

    public function submitAnswer(SubmitPracticeAnswerRequest $request)
    {
        return response()->json(
            $this->practiceSessionService->submitAnswer(
                $request->user(),
                (int) $request->validated('question_id'),
                $request->validated('answer'),
            )
        );
    }

    public function submitSpeaking(SubmitPracticeSpeakingRequest $request)
    {
        return response()->json(
            $this->practiceSessionService->submitSpeaking(
                $request->user(),
                (int) $request->validated('question_id'),
                $request->validated('audio'),
            )
        );
    }
}
