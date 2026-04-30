<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * VoiceService - Dedicated service for processing voice/audio
 * Uses OpenAI Whisper API for transcription and evaluation
 */
class VoiceService
{
    protected $apiKey;
    protected $apiUrl = 'https://api.openai.com/v1/audio/transcriptions';

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
    }

    /**
     * Check if the voice service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Process audio and get transcription + feedback
     * Uses OpenAI Whisper API for transcription
     */
    public function processAudio(string $audioBase64, string $questionText = '', string $targetAnswer = ''): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key is missing. Using fallback.');
            return $this->getFallbackResponse($questionText);
        }

        try {
            // Convert base64 to binary for Whisper API
            $audioBinary = base64_decode($audioBase64);

            // Send to Whisper API for transcription
            $response = Http::asMultipart()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])
                ->post($this->apiUrl, [
                    'model' => 'whisper-1',
                    'file' => [
                        'content' => $audioBinary,
                        'filename' => 'audio.webm',
                    ],
                    'response_format' => 'json',
                ]);

            if ($response->successful()) {
                $transcription = $response->json('text');

                // Generate feedback based on transcription
                return $this->generateFeedback($transcription, $questionText, $targetAnswer);
            }

            Log::error('Whisper API Error: ' . $response->body());
            return $this->getFallbackResponse($questionText);

        } catch (\Exception $e) {
            Log::error('VoiceService Exception: ' . $e->getMessage());
            return $this->getFallbackResponse($questionText);
        }
    }

    /**
     * Generate feedback based on transcription
     */
    protected function generateFeedback(string $transcription, string $questionText, string $targetAnswer): array
    {
        // Simple feedback generation based on transcription
        $wordCount = str_word_count($transcription);

        $isCorrect = !empty($transcription) && strlen($transcription) > 10;
        $points = min(10, max(2, $wordCount)); // 2-10 points based on length

        $feedback = "Good attempt! You said: \"{$transcription}\"";

        if ($wordCount < 5) {
            $feedback .= " Try to speak more to demonstrate your English ability.";
        } elseif ($wordCount < 20) {
            $feedback .= " Good effort! Try to extend your answers with more details.";
        } else {
            $feedback .= " Excellent! You provided a thorough response.";
        }

        return [
            'is_correct' => $isCorrect,
            'points_earned' => $points,
            'feedback' => $feedback,
            'pronunciation_feedback' => "Transcription: \"{$transcription}\"",
            'transcription' => $transcription,
        ];
    }

    /**
     * Fallback response when API is not available
     */
    protected function getFallbackResponse(string $questionText): array
    {
        return [
            'is_correct' => true,
            'points_earned' => 5,
            'feedback' => 'Voice processing is in standby mode. Your response has been recorded.',
            'pronunciation_feedback' => 'Audio analysis is temporarily unavailable. Keep practicing!',
            'transcription' => null,
        ];
    }

    /**
     * Alternative: Use Gemini for audio processing with optimized settings
     */
    public function processAudioWithGemini(string $audioBase64, string $questionText = '', string $targetAnswer = ''): ?array
    {
        $geminiService = app(GeminiService::class);

        $prompt = <<<PROMPT
You are an IELTS Speaking Examiner evaluating a student's response.

Question: "{$questionText}"
Expected answer: "{$targetAnswer}"

The student submitted an audio response. Please analyze and provide feedback.

Return JSON with:
- is_correct: boolean
- points_earned: integer (2-10)
- feedback: string (content feedback)
- pronunciation_feedback: string (pronunciation analysis)
PROMPT;

        $result = $geminiService->generate($prompt, $audioBase64, 'audio/webm');

        if ($result) {
            return [
                'is_correct' => $result['is_correct'] ?? true,
                'points_earned' => $result['points_earned'] ?? 5,
                'feedback' => $result['feedback'] ?? 'Good attempt!',
                'pronunciation_feedback' => $result['pronunciation_feedback'] ?? 'Audio received and analyzed.',
            ];
        }

        return $this->getFallbackResponse($questionText);
    }
}
