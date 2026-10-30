<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'statement_en',
    'statement_ar',
    'exam_type_id',
    'question_area_id',
    'profile_type_id',
    'status',
])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ExamType, $this>
     */
    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    /**
     * @return BelongsTo<QuestionArea, $this>
     */
    public function questionArea(): BelongsTo
    {
        return $this->belongsTo(QuestionArea::class);
    }

    /**
     * @return BelongsTo<ProfileType, $this>
     */
    public function profileType(): BelongsTo
    {
        return $this->belongsTo(ProfileType::class);
    }

    /**
     * @return HasMany<AnswerOption, $this>
     */
    public function answerOptions(): HasMany
    {
        return $this->hasMany(AnswerOption::class);
    }
}
