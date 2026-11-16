<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExamPartSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'exam_part_id',
    'question_type_id',
    'question_count',
    'position',
])]
class ExamPartSection extends Model
{
    /** @use HasFactory<ExamPartSectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'question_count' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExamPart, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(ExamPart::class, 'exam_part_id');
    }

    /**
     * @return BelongsTo<QuestionType, $this>
     */
    public function questionType(): BelongsTo
    {
        return $this->belongsTo(QuestionType::class);
    }
}
