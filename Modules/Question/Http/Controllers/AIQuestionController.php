<?php

namespace Modules\Question\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Speaking\Services\AiSpeakingService;
use Modules\Question\Models\Question;
use Illuminate\Http\Request;

class AIQuestionController extends Controller
{
    protected $aiService;

    public function __construct(AiSpeakingService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Show the generator interface.
     */
    public function index()
    {
        return view('question::admin.generator');
    }

    /**
     * Generate questions using AI.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'skill' => 'required|string',
            'topic' => 'required|string',
            'count' => 'integer|min:1|max:5',
        ]);

        $prompt = $this->buildGeneratorPrompt($request->skill, $request->topic, $request->count);
        $questions = $this->aiService->generate($prompt);

        if (!$questions) {
            return response()->json(['error' => 'AI Generation failed.'], 500);
        }

        return response()->json($questions);
    }

    /**
     * Save generated questions.
     */
    public function store(Request $request)
    {
        $request->validate([
            'questions' => 'required|array',
            'questions.*.skill' => 'required',
            'questions.*.type' => 'required',
            'questions.*.content' => 'required',
        ]);

        foreach ($request->questions as $qData) {
            $content = $qData['content'];
            if (isset($qData['audio_path'])) {
                $content['audio_path'] = $qData['audio_path'];
            }

            Question::create([
                'skill' => $qData['skill'],
                'type' => $qData['type'],
                'topic' => $qData['topic'] ?? 'General',
                'content' => $content,
                'difficulty' => $qData['difficulty'] ?? 'medium',
            ]);
        }

        return response()->json(['message' => 'Questions saved successfully!']);
    }

    protected function buildGeneratorPrompt($skill, $topic, $count)
    {
        return <<<PROMPT
You are an expert IELTS content creator. 
Task: Generate {$count} IELTS practice questions for the skill "{$skill}" on the topic "{$topic}".

Return the response STRICTLY as a raw JSON array of objects. 
DO NOT include any markdown formatting, backticks (```), or conversational text like "Here are your questions:".
Each object in the array MUST have:
- skill: "{$skill}"
- type: (string) e.g., "mcq" or "gap_fill"
- topic: "{$topic}"
- difficulty: "medium"
- content: (object) {
    question: (string) The full question text or passage snippet.
    answer: (string) The correct answer.
    options: (array of strings, only for mcq type)
    explanation: (string) Why this is the correct answer.
  }

Ensure the questions are realistic and suitable for IELTS Band 6.5-7.5.
PROMPT;
    }
}
