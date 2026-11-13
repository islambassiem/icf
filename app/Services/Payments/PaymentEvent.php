<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** A provider-neutral result: "this checkout was paid" or "this checkout failed". */
final readonly class PaymentEvent
{
    public function __construct(
        public string $checkoutId,
        public string $status,              // 'paid' | 'failed'
        public ?string $providerPaymentId = null,
        public ?string $reason = null,      // e.g. declined, insufficient_funds, canceled
    ) {}

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
