<?php

namespace App\Models;

use Database\Factories\AnswerOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'question_id',
    'statement_en',
    'statement_ar',
    'comment_en',
    'comment_ar',
    'is_best',
    'is_worst',
])]
class AnswerOption extends Model
{
    /** @use HasFactory<AnswerOptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_best' => 'boolean',
            'is_worst' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
