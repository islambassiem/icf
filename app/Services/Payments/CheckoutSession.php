<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** What a gateway returns when a checkout is created. */
final readonly class CheckoutSession
{
    public function __construct(
        public string $id,   // the provider's checkout id (saved as payments.provider_checkout_id)
        public string $url,  // where to send the customer to pay
    ) {}
}
