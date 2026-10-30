<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyEnum;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name_en',
    'name_ar',
    'duration_days',
    'price',
    'currency',
    'is_active',
])]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    /**
     * @return array{currency: string}
     */
    protected function casts(): array
    {
        return [
            'currency' => CurrencyEnum::class,
        ];
    }
}
