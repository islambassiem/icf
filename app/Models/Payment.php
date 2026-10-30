<?php

namespace App\Models;

use App\Enums\CurrencyEnum;
use App\Enums\PaymentStatusEnum;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'subscription_id',
    'provider',
    'provider_payment_id',
    'provider_checkout_id',
    'amount',
    'currency',
    'status',
    'paid_at',
    'failure_reason',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts()
    {
        return [
            'currency' => CurrencyEnum::class,
            'status' => PaymentStatusEnum::class,
            'paid_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
