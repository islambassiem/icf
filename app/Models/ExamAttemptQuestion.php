<?php

namespace App\Models;

use Database\Factories\ExamAttemptQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'attempt_id',
    'question_id',
    'part_number',
    'position',
    'question_snapshot',
])]
class ExamAttemptQuestion extends Model
{
    /** @use HasFactory<ExamAttemptQuestionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'part_number' => 'integer',
            'position' => 'integer',
            'question_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return HasOne<ExamResponse, $this>
     */
    public function response(): HasOne
    {
        return $this->hasOne(ExamResponse::class);
    }
}
