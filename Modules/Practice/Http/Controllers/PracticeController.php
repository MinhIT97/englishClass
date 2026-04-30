<?php

namespace Modules\Practice\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Question\Models\Question;
use Modules\Practice\Models\UserAnswer;
use Modules\Gamification\Services\GamificationService;
use Modules\Speaking\Services\AiSpeakingService;
use App\Services\AI\VoiceService;
use Illuminate\Http\Request;

class PracticeController extends Controller
{
    protected $gamificationService;
    protected $geminiService;
    protected $voiceService;

    public function __construct(GamificationService $gamificationService, AiSpeakingService $geminiService, VoiceService $voiceService)
    {
        $this->gamificationService = $gamificationService;
        $this->geminiService = $geminiService;
        $this->voiceService = $voiceService;
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

        // Auto-generate audio for listening if missing
        if ($skill === 'listening' && !isset($question->content['audio_path'])) {
            $this->ensureAudioExists($question);
        }

        return view('practice::drill', compact('question', 'skill'));
    }

    /**
     * Ensure listening question has an audio file.
     */
    protected function ensureAudioExists(Question $question)
    {
        $text = $question->content['text'] ?? $question->content['question'] ?? '';
        $answer = $question->content['answer'] ?? '';

        // Clean text: remove hints and fill gaps
        $cleanText = preg_replace('/\s*\(Audio transcript hint:.*?\)\s*/i', '', $text);
        $cleanText = str_replace(['[____]', '[___]', '[__]', '[blank]'], $answer, $cleanText);

        $audioPath = $this->geminiService->generateVoice($cleanText);

        if ($audioPath) {
            $content = $question->content;
            $content['audio_path'] = $audioPath;
            $question->content = $content;
            $question->save();
        }
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
    public function submitSpeaking(Request $request)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'audio' => 'required|string', // base64
        ]);

        $question = Question::findOrFail($request->question_id);
        $audioBase64 = $request->get('audio');
        $questionText = $question->content['question'] ?? $question->content['text'] ?? 'N/A';
        $targetAnswer = $question->content['answer'] ?? 'General response';

        // Use VoiceService for audio processing (supports both OpenAI Whisper and Gemini)
        $aiResult = $this->voiceService->processAudioWithGemini($audioBase64, $questionText, $targetAnswer);

        $isCorrect = $aiResult['is_correct'] ?? true;
        $points = $aiResult['points_earned'] ?? 5;

        UserAnswer::create([
            'user_id' => auth()->id(),
            'question_id' => $question->id,
            'student_answer' => '[Audio Response]',
            'is_correct' => $isCorrect,
            'points_earned' => $points,
        ]);

        $this->gamificationService->awardPoints(auth()->user(), $points);

        return response()->json([
            'is_correct' => $isCorrect,
            'correct_answer' => $question->content['answer'] ?? 'N/A',
            'points_earned' => $points,
            'feedback' => $aiResult['feedback'] ?? 'Thank you for your response.',
            'pronunciation_feedback' => $aiResult['pronunciation_feedback'] ?? 'Could not analyze pronunciation.',
        ]);
    }
}
