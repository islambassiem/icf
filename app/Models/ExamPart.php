<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExamPartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'exam_type_id',
    'part_number',
    'minutes',
    'break_minutes',
])]
class ExamPart extends Model
{
    /** @use HasFactory<ExamPartFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'part_number' => 'integer',
            'minutes' => 'integer',
            'break_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExamType, $this>
     */
    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    /**
     * @return HasMany<ExamPartSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(ExamPartSection::class)->orderBy('position');
    }
}
