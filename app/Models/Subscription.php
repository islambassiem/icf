<?php

namespace App\Models;

use App\Enums\CurrencyEnum;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Date;

#[Fillable([
    'user_id',
    'subscription_plan_id',
    'status',
    'starts_at',
    'ends_at',
    'amount_paid',
    'currency',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    // Align these with the values your checkout writes to `subscriptions.status`.
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELED = 'canceled';

    // Set when the payment for a pending subscription fails.
    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'amount_paid' => 'decimal:2',
            'currency' => CurrencyEnum::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Paid and running right now.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function daysLeft(): int
    {
        return max(0, (int) ceil(now()->diffInDays($this->ends_at, false)));
    }

    /** 0-100: how much of the paid period has passed. */
    public function elapsedPercent(): int
    {
        $total = Date::parse($this->starts_at)->diffInSeconds($this->ends_at);

        return $total > 0
            ? (int) min(100, max(0, round(Date::parse($this->starts_at)->diffInSeconds(now()) / $total * 100)))
            : 100;
    }
}
