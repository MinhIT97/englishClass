<?php

namespace Modules\Practice\Services;

use App\Models\User;
use App\Services\AI\VoiceService;
use Modules\Gamification\Services\GamificationService;
use Modules\Practice\Models\UserAnswer;
use Modules\Question\Models\Question;
use Modules\Speaking\Services\AiSpeakingService;
use Modules\Writing\Services\WritingGraderService;

class PracticeSessionService
{
    public function __construct(
        private readonly GamificationService $gamificationService,
        private readonly AiSpeakingService $speakingService,
        private readonly VoiceService $voiceService,
        private readonly WritingGraderService $writingGraderService,
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

        if ($question->skill === 'writing') {
            return $this->submitWritingAnswer($user, $question, $studentAnswer);
        }

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

    private function submitWritingAnswer(User $user, Question $question, string $essayContent): array
    {
        $taskType = in_array($question->type, ['task_1', 'task_2'], true) ? $question->type : 'task_2';
        $attempt = $this->writingGraderService->gradeEssay($user->id, $essayContent, $taskType);

        if (!$attempt) {
            return [
                'is_correct' => false,
                'correct_answer' => 'AI grading is temporarily unavailable.',
                'points_earned' => 0,
                'feedback' => 'We could not score your writing just now. Please try again in a moment.',
            ];
        }

        $bandScore = (float) ($attempt->band_score ?? 0);
        $isCorrect = $bandScore >= 6.0;
        $points = max(4, (int) round($bandScore * 2));

        UserAnswer::query()->create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'student_answer' => $essayContent,
            'is_correct' => $isCorrect,
            'points_earned' => $points,
        ]);

        $this->gamificationService->awardPoints($user, $points);

        $feedbackParts = [];
        foreach ((array) ($attempt->feedback ?? []) as $label => $text) {
            if (is_string($text) && trim($text) !== '') {
                $feedbackParts[] = '<strong>' . ucfirst(str_replace('_', ' ', $label)) . ':</strong> ' . e($text);
            }
        }

        $feedback = '<strong>Estimated Band:</strong> ' . number_format($bandScore, 1);

        if ($feedbackParts !== []) {
            $feedback .= '<br><br>' . implode('<br><br>', $feedbackParts);
        }

        return [
            'is_correct' => $isCorrect,
            'correct_answer' => 'Your essay has been graded by AI.',
            'points_earned' => $points,
            'feedback' => $feedback,
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
