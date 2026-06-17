<?php

namespace Modules\TelegramBot\Services;

use Carbon\Carbon;
use Modules\TelegramBot\Models\ReviewSchedule;

/**
 * Implements the SM-2 spaced repetition algorithm.
 *
 * Grades: 0 = Again, 1 = Hard, 2 = Good, 3 = Easy.
 * Reference: https://super-memory.com/english/ol/sm2.htm
 */
class SpacedRepetitionService
{
    private const MIN_EASE_FACTOR = 1.30;

    /**
     * Apply a grade to the schedule and persist it.
     */
    public function grade(ReviewSchedule $schedule, int $grade): ReviewSchedule
    {
        $grade = max(0, min(3, $grade));

        if ($grade < ReviewSchedule::GRADE_GOOD) {
            // Failed: reset repetitions, repeat today.
            $schedule->repetitions = 0;
            $schedule->interval_days = 0;
        } else {
            if ($schedule->repetitions === 0) {
                $schedule->interval_days = 1;
            } elseif ($schedule->repetitions === 1) {
                $schedule->interval_days = 3;
            } else {
                $schedule->interval_days = (int) round(
                    $schedule->interval_days * (float) $schedule->ease_factor
                );
            }
            $schedule->repetitions++;
        }

        // Update ease factor using the SM-2 formula.
        $ef = (float) $schedule->ease_factor
            + (0.1 - (3 - $grade) * (0.08 + (3 - $grade) * 0.02));
        $schedule->ease_factor = max(self::MIN_EASE_FACTOR, round($ef, 2));

        $schedule->last_grade = $grade;
        $schedule->last_reviewed_at = Carbon::now();
        $schedule->next_review_at = Carbon::now()->addDays($schedule->interval_days);
        $schedule->save();

        return $schedule;
    }

    /**
     * Return the grade label for a Telegram inline button.
     *
     * @return array{label: string, callback: string}
     */
    public function gradeButton(int $grade, int $scheduleId): array
    {
        $labels = [
            ReviewSchedule::GRADE_AGAIN => '🔁 Lại',
            ReviewSchedule::GRADE_HARD => '😣 Khó',
            ReviewSchedule::GRADE_GOOD => '👍 Tốt',
            ReviewSchedule::GRADE_EASY => '🎉 Dễ',
        ];

        return [
            'label' => $labels[$grade] ?? '?',
            'callback' => "tgb:r:{$scheduleId}:{$grade}",
        ];
    }

    /**
     * Build a Telegram inline keyboard row with all four grade buttons.
     */
    public function gradeKeyboard(int $scheduleId): array
    {
        return [
            array_map(fn (int $g) => [
                'text' => $this->gradeButton($g, $scheduleId)['label'],
                'callback_data' => $this->gradeButton($g, $scheduleId)['callback'],
            ], [
                ReviewSchedule::GRADE_AGAIN,
                ReviewSchedule::GRADE_HARD,
                ReviewSchedule::GRADE_GOOD,
                ReviewSchedule::GRADE_EASY,
            ]),
        ];
    }
}
