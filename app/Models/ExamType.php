<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Database\Factories\ExamTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name_en',
    'name_ar',
    'description',
])]
class ExamType extends Model
{
    /** @use HasFactory<ExamTypeFactory> */
    use HasFactory;

    use HasLocalizedName;

    /**
     * The parts of this exam (timing and what each part contains), in order.
     *
     * @return HasMany<ExamPart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(ExamPart::class)->orderBy('part_number');
    }
}
