<?php

namespace Modules\Question\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\IeltsTopicCatalog;
use Modules\Speaking\Services\AiTextService;
use Modules\Question\Models\Question;
use Illuminate\Http\Request;

class AIQuestionController extends Controller
{
    protected $aiService;

    public function __construct(AiTextService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Show the generator interface.
     */
    public function index()
    {
        return view('question::admin.generator', [
            'topics' => IeltsTopicCatalog::names(),
            'bands' => ['5.0-5.5', '6.0-6.5', '7.0-7.5', '8.0+'],
        ]);
    }

    /**
     * Generate questions using AI.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'skill' => 'required|string',
            'topic' => 'required|string',
            'band' => 'nullable|string|max:20',
            'count' => 'integer|min:1|max:30',
        ]);

        $prompt = $this->buildGeneratorPrompt(
            $request->skill,
            $request->topic,
            $request->count,
            $request->string('band')->toString() ?: '6.5-7.5'
        );
        $questions = $this->aiService->generateRaw($prompt);

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

    protected function buildGeneratorPrompt($skill, $topic, $count, $band)
    {
        $topicGuide = IeltsTopicCatalog::get($topic);
        $vocabulary = $topicGuide ? implode(', ', $topicGuide['vocabulary']) : 'Use topic-appropriate academic vocabulary.';
        $sampleQuestions = $topicGuide ? implode(' | ', $topicGuide['questions']) : 'Generate realistic IELTS learner-facing prompts.';
        $writingTask = $topicGuide['writing_task'] ?? 'Create IELTS-appropriate writing prompts for this topic.';

        $skillInstructions = match ($skill) {
            'reading' => 'Return mostly mcq reading passages with clear options and one correct answer.',
            'listening' => 'Return gap_fill listening items with natural short transcripts and one precise answer.',
            'writing' => 'Return realistic writing prompts using task_1 or task_2 as type and store the prompt in content.text.',
            'speaking' => 'Return speaking prompts using part_1, part_2, or part_3 as type and store the prompt in content.text.',
            default => 'Return IELTS practice content that matches the selected skill.',
        };

        return <<<PROMPT
You are an IELTS expert and expert IELTS content creator.
Task: Generate {$count} IELTS practice items for the skill "{$skill}" on the topic "{$topic}".
Target learner level: IELTS Band {$band}.

Return the response STRICTLY as a raw JSON array of objects. 
DO NOT include any markdown formatting, backticks (```), or conversational text like "Here are your questions:".

Content requirements:
- Use natural English suitable for IELTS learners.
- Avoid repetition across the generated set.
- Ensure difficulty increases gradually across the set.
- Weave in topic-specific vocabulary when appropriate: {$vocabulary}
- Speaking/question angles to keep in mind: {$sampleQuestions}
- Writing direction to keep in mind: {$writingTask}
- {$skillInstructions}

Each object in the array MUST have:
- skill: "{$skill}"
- type: (string) e.g., "mcq" or "gap_fill"
- topic: "{$topic}"
- difficulty: "easy", "medium", or "hard"
- content: (object) {
    question: (string) The full question text or passage snippet when applicable.
    text: (string) Use this key for writing/speaking prompts if question is not suitable.
    answer: (string) The correct answer or expected answer direction.
    options: (array of strings, only for mcq type)
    explanation: (string) Why this is the correct answer or what a strong response should cover.
  }

Make the output immediately usable in an IELTS learning app.
PROMPT;
    }
}
