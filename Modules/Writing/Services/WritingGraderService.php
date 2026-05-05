<?php

namespace Modules\Writing\Services;

use Modules\Writing\Models\WritingAttempt;
use Modules\Speaking\Services\AiSpeakingService;
use Illuminate\Support\Facades\Log;

class WritingGraderService
{
    protected $aiService;

    public function __construct(AiSpeakingService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Grade an essay and return feedback.
     */
    public function gradeEssay(int $userId, string $essayContent, string $taskType = 'task_2')
    {
        $prompt = $this->buildPrompt($essayContent, $taskType);
        $aiResult = $this->aiService->generate($prompt);

        if (!$aiResult) {
            Log::error('AI Grading failed for user ' . $userId);
            return null;
        }

        return WritingAttempt::create([
            'user_id' => $userId,
            'task_type' => $taskType,
            'essay_content' => $essayContent,
            'band_score' => $aiResult['band_score'] ?? 0,
            'feedback' => $aiResult['feedback'] ?? [],
            'revised_essay' => $aiResult['revised_essay'] ?? null,
        ]);
    }

    /**
     * Build the AI prompt for IELTS grading.
     */
    protected function buildPrompt(string $content, string $taskType)
    {
        return <<<PROMPT
You are an expert IELTS Writing Examiner. 
Task: Grade the following student essay for IELTS {$taskType}.
Content: "{$content}"

Return the response strictly in JSON format with the following keys:
- band_score (float): Overall band score (0-9.0).
- feedback (object): {
    grammar: (string) Specific feedback on grammatical range and accuracy.
    vocabulary: (string) Feedback on lexical resource.
    coherence: (string) Feedback on coherence and cohesion.
    task_response: (string) Feedback on task achievement/response.
  }
- revised_essay (string): A "Band 8.0+" version of the student's essay for reference.

Focus on being encouraging but precise. Use realistic IELTS standards.
PROMPT;
    }
}
