<?php

namespace Modules\Speaking\Services;

use App\Services\AI\GeminiService;
use Modules\Speaking\Models\SpeakingSession;
use Modules\Speaking\Models\Transcript;
use Illuminate\Support\Facades\Log;

class SpeakingService
{
    protected $aiService;

    public function __construct(GeminiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Start a new speaking session.
     */
    public function startSession($userId)
    {
        return SpeakingSession::create([
            'user_id' => $userId,
            'started_at' => now(),
        ]);
    }

    /**
     * Get the next question from AI based on conversation history.
     */
    public function getNextResponse(SpeakingSession $session, string $studentInput = null)
    {
        $history = $session->transcripts()->orderBy('created_at', 'asc')->get();
        $prompt = $this->buildSpeakingPrompt($history, $studentInput);
        
        $aiResult = $this->aiService->generate($prompt);

        if (!$aiResult || !isset($aiResult['response'])) {
            // Fallback for safety
            return Transcript::create([
                'session_id' => $session->id,
                'content' => "I'm sorry, I'm having a bit of trouble hearing you clearly. Could you repeat that or tell me more?",
                'feedback' => null,
            ]);
        }

        // Save AI response
        return Transcript::create([
            'session_id' => $session->id,
            'content' => $aiResult['response'],
            'feedback' => $aiResult['feedback'] ?? null,
        ]);
    }

    protected function buildSpeakingPrompt($history, $studentInput)
    {
        $context = "";
        foreach($history as $item) {
            $context .= "AI: " . $item->content . "\n";
        }
        if($studentInput) $context .= "Student: " . $studentInput . "\n";

        return <<<PROMPT
You are an IELTS Speaking Examiner (Persona: friendly but professional). 
Context of conversation:
{$context}

Task: Continue the IELTS Speaking Part 1 or Part 2 interview.
If this is the start, introduce yourself and ask a common Part 1 question (e.g., home, work, hobbies).
If continuing, acknowledge the student's answer briefly and ask a follow-up or a new question.

Return the response strictly in JSON:
- response (string): Your next question or comment as the examiner.
- feedback (object|null): If student provided input, give brief feedback:
    {
        grammar_correction: (string|null) A minor correction if they made a mistake.
        tip: (string|null) One tip to improve their specific answer.
    }

Keep your responses natural and concise.
PROMPT;
    }
}
