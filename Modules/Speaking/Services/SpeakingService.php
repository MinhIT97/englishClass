<?php

namespace Modules\Speaking\Services;

use App\Services\AI\GeminiService;
use App\Services\AI\VoiceService;
use Modules\Speaking\Models\SpeakingSession;
use Modules\Speaking\Models\Transcript;
use Illuminate\Support\Facades\Log;

class SpeakingService
{
    protected $aiService;
    protected $voiceService;

    public function __construct(GeminiService $aiService, VoiceService $voiceService)
    {
        $this->aiService = $aiService;
        $this->voiceService = $voiceService;
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
    public function getNextResponse(SpeakingSession $session, string $studentInput = null, string $audioBase64 = null)
    {
        $history = $session->transcripts()->orderBy('created_at', 'asc')->get();
        $prompt = $this->buildSpeakingPrompt($history, $studentInput, (bool)$audioBase64);

        $aiResult = $this->aiService->generate($prompt, $audioBase64);

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

    protected function buildSpeakingPrompt($history, $studentInput, bool $hasAudio = false)
    {
        $context = "";
        foreach($history as $item) {
            $context .= "AI: " . $item->content . "\n";
        }
        if($studentInput) $context .= "Student (Transcription): " . $studentInput . "\n";

        $audioInstruction = $hasAudio ? "I have attached the student's actual audio input. Please listen carefully to their pronunciation, intonation, and delivery speed." : "";

        return <<<PROMPT
You are an IELTS Speaking Examiner (Persona: friendly but professional).
Context of conversation:
{$context}

{$audioInstruction}

Task: Continue the IELTS Speaking Part 1 or Part 2 interview.
- If this is the start, introduce yourself and ask a common Part 1 question.
- If continuing, acknowledge the student's answer briefly and ask a follow-up or a new question.

Return the response strictly in JSON:
- response (string): Your next question or comment as the examiner.
- feedback (object|null): If student provided input, give specific feedback:
    {
        grammar_correction: (string|null) Minor correction if needed.
        pronunciation: (string|null) ONLY if audio is provided: Mention if they mispronounced words or sounded unnatural.
        tip: (string|null) One specific tip to improve their score (fluency, vocab, or grammar).
    }

Keep your responses natural and concise.
PROMPT;
    }
}
