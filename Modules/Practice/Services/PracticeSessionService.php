<?php

namespace Modules\Practice\Services;

use App\Models\User;
use App\Services\AI\VoiceService;
use Modules\Gamification\Services\GamificationService;
use Modules\Practice\Models\UserAnswer;
use Modules\Question\Models\Question;
use Modules\Speaking\Services\AiSpeakingService;

class PracticeSessionService
{
    public function __construct(
        private readonly GamificationService $gamificationService,
        private readonly AiSpeakingService $speakingService,
        private readonly VoiceService $voiceService,
    ) {
    }

    public function loadDrill(string $skill): ?Question
    {
        $question = Question::query()
            ->forSkill($skill)
            ->inRandomOrder()
            ->first();

        if (!$question) {
            return null;
        }

        if ($skill === 'listening' && !isset($question->content['audio_path'])) {
            $this->ensureAudioExists($question);
        }

        return $question;
    }

    public function submitAnswer(User $user, int $questionId, string $studentAnswer): array
    {
        $question = Question::query()->findOrFail($questionId);
        $correctAnswer = (string) ($question->content['answer'] ?? '');
        $isCorrect = strcasecmp(trim($studentAnswer), trim($correctAnswer)) === 0;
        $points = $isCorrect ? 10 : 2;

        UserAnswer::query()->create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'student_answer' => $studentAnswer,
            'is_correct' => $isCorrect,
            'points_earned' => $points,
        ]);

        $this->gamificationService->awardPoints($user, $points);

        return [
            'is_correct' => $isCorrect,
            'correct_answer' => $correctAnswer,
            'points_earned' => $points,
            'feedback' => $question->content['explanation'] ?? ($isCorrect ? 'Well done!' : 'Keep practicing!'),
        ];
    }

    public function submitSpeaking(User $user, int $questionId, string $audioBase64): array
    {
        $question = Question::query()->findOrFail($questionId);
        $questionText = $question->content['question'] ?? $question->content['text'] ?? 'N/A';
        $targetAnswer = $question->content['answer'] ?? 'General response';

        $aiResult = $this->voiceService->processAudioWithGemini($audioBase64, $questionText, $targetAnswer);
        $isCorrect = $aiResult['is_correct'] ?? true;
        $points = $aiResult['points_earned'] ?? 5;

        UserAnswer::query()->create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'student_answer' => '[Audio Response]',
            'is_correct' => $isCorrect,
            'points_earned' => $points,
        ]);

        $this->gamificationService->awardPoints($user, $points);

        return [
            'is_correct' => $isCorrect,
            'correct_answer' => $question->content['answer'] ?? 'N/A',
            'points_earned' => $points,
            'feedback' => $aiResult['feedback'] ?? 'Thank you for your response.',
            'pronunciation_feedback' => $aiResult['pronunciation_feedback'] ?? 'Could not analyze pronunciation.',
        ];
    }

    private function ensureAudioExists(Question $question): void
    {
        $text = $question->content['text'] ?? $question->content['question'] ?? '';
        $answer = $question->content['answer'] ?? '';

        $cleanText = preg_replace('/\s*\(Audio transcript hint:.*?\)\s*/i', '', $text);
        $cleanText = str_replace(['[____]', '[___]', '[__]', '[blank]'], $answer, $cleanText);

        $audioPath = $this->speakingService->generateTTS($cleanText);

        if (!$audioPath) {
            return;
        }

        $content = $question->content;
        $content['audio_path'] = $audioPath;
        $question->content = $content;
        $question->save();
    }
}
