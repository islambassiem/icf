<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Database\Factories\QuestionTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name_en',
    'name_ar',
    'mode',
    'best_points',
    'worst_points',
])]
class QuestionType extends Model
{
    /** @use HasFactory<QuestionTypeFactory> */
    use HasFactory;

    use HasLocalizedName;

    /** One correct answer (the option flagged is_best). */
    public const MODE_SINGLE = 'single';

    /** A best answer and a worst answer. */
    public const MODE_BEST_WORST = 'best_worst';

    protected function casts(): array
    {
        return [
            'best_points' => 'integer',
            'worst_points' => 'integer',
        ];
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function isSingle(): bool
    {
        return $this->mode === self::MODE_SINGLE;
    }

    /** Most points one question of this type can earn. */
    public function maxPoints(): int
    {
        return $this->isSingle() ? $this->best_points : $this->best_points + $this->worst_points;
    }
}
