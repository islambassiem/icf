<?php

namespace App\Models;

use Database\Factories\ExamResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'exam_attempt_question_id',
    'best_answer_option_id',
    'worst_answer_option_id',
    'answered_at',
])]
class ExamResponse extends Model
{
    /** @use HasFactory<ExamResponseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExamAttemptQuestion, $this>
     */
    public function attemptQuestion(): BelongsTo
    {
        return $this->belongsTo(
            ExamAttemptQuestion::class,
            'exam_attempt_question_id'
        );
    }

    /**
     * @return BelongsTo<AnswerOption, $this>
     */
    public function bestAnswerOption(): BelongsTo
    {
        return $this->belongsTo(
            AnswerOption::class,
            'best_answer_option_id'
        );
    }

    /**
     * @return BelongsTo<AnswerOption, $this>
     */
    public function worstAnswerOption(): BelongsTo
    {
        return $this->belongsTo(
            AnswerOption::class,
            'worst_answer_option_id'
        );
    }
}
