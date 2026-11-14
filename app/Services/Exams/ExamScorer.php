<?php

declare(strict_types=1);

namespace App\Services\Exams;

use App\Models\ExamAttempt;

/**
 * Scoring rule: the best answer and the worst answer are worth one point each per question.
 * Unanswered questions score 0 but still count in the total.
 */
class ExamScorer
{
    /** Score on the portal.score_max scale (100 = percentage). */
    public function score(ExamAttempt $attempt): int
    {
        $total = max(1, $attempt->attemptQuestions()->count());

        return (int) round($this->points($attempt) / (2 * $total) * max(1, (int) config('portal.score_max')));
    }

    public function points(ExamAttempt $attempt): int
    {
        $points = 0;

        foreach ($attempt->attemptQuestions()->with('response')->get() as $question) {
            $points += $question->points();
        }

        return $points;
    }
}
