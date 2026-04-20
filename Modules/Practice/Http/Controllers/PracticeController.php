<?php

namespace Modules\Practice\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Question\Models\Question;
use Modules\Practice\Models\UserAnswer;
use Modules\Gamification\Services\GamificationService;
use Illuminate\Http\Request;

class PracticeController extends Controller
{
    protected $gamificationService;

    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }

    /**
     * Show general practice dashboard.
     */
    public function index()
    {
        return view('practice::index');
    }

    /**
     * Show a practice drill for a specific skill.
     */
    public function showDrill(string $skill)
    {
        $question = Question::where('skill', $skill)->inRandomOrder()->first();

        if (!$question) {
            return redirect()->route('student.practice.index')->with('error', "No questions available for {$skill} yet.");
        }

        return view('practice::drill', compact('question', 'skill'));
    }

    /**
     * Submit an answer to a question.
     */
    public function submitAnswer(Request $request)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer' => 'required|string',
        ]);

        $question = Question::findOrFail($request->question_id);
        $studentAnswer = $request->get('answer');
        
        // Basic check for MCQ and Gap Fill
        $correctAnswer = $question->content['answer'] ?? '';
        $isCorrect = strtolower(trim($studentAnswer)) === strtolower(trim($correctAnswer));
        
        $points = $isCorrect ? 10 : 2; // 10 XP for correct, 2 XP for effort

        $userAnswer = UserAnswer::create([
            'user_id' => auth()->id(),
            'question_id' => $question->id,
            'student_answer' => $studentAnswer,
            'is_correct' => $isCorrect,
            'points_earned' => $points,
        ]);

        $this->gamificationService->awardPoints(auth()->user(), $points);

        return response()->json([
            'is_correct' => $isCorrect,
            'correct_answer' => $correctAnswer,
            'points_earned' => $points,
            'feedback' => $question->content['explanation'] ?? ($isCorrect ? 'Well done!' : 'Keep practicing!'),
        ]);
    }
}
