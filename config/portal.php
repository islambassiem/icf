<?php

declare(strict_types=1);

return [
    // The exam, as described in the BRD.
    'exam' => [
        'parts' => 2,
        'questions_per_part' => 40,
        'minutes_per_part' => 70,
        'break_minutes' => 10,
    ],

    // Only questions with this value in questions.status are used in exams.
    // Leave empty ("") to use every question. Set PORTAL_QUESTION_STATUS to match your wording.
    'question_status' => env('PORTAL_QUESTION_STATUS', 'active'),

    // Highest possible value of exam_attempts.score. Use 100 if you store a percentage,
    // or the maximum points (e.g. 160) if you store raw points.
    'score_max' => (int) env('PORTAL_SCORE_MAX', 100),

    // Show the "renew" prompt when this many days or fewer are left.
    'renew_warning_days' => 7,
];
