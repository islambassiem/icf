<?php

declare(strict_types=1);

namespace App\Services\Exams;

use App\Models\ExamAttempt;

/**
 * Scoring uses the points frozen into each question when the exam started
 * (for example: knowledge = 1 point, situational = 1 for the best answer + 1 for the worst answer).
 * Unanswered questions score 0 but still count towards the maximum.
 */
class ExamScorer
{
    /** Score on the portal.score_max scale (100 = percentage). */
    public function score(ExamAttempt $attempt): int
    {
        return (int) round($this->points($attempt) / max(1, $this->maxPoints($attempt)) * max(1, (int) config('portal.score_max')));
    }

    public function points(ExamAttempt $attempt): int
    {
        return (int) $attempt->attemptQuestions()->with('response')->get()->sum(fn ($question): int => $question->points());
    }

    public function maxPoints(ExamAttempt $attempt): int
    {
        return $attempt->maxPoints();
    }
}
