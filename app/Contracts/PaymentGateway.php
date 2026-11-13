<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Payment;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\InvalidWebhookException;
use App\Services\Payments\PaymentEvent;
use Illuminate\Http\Request;

/**
 * Everything the portal needs from a payment provider. Write one class per provider.
 */
interface PaymentGateway
{
    /** Short name stored in payments.provider, e.g. "fake", "moyasar". */
    public function name(): string;

    /** Create a hosted checkout for this payment and say where to send the customer. */
    public function createCheckout(Payment $payment, string $returnUrl): CheckoutSession;

    /**
     * Verify a webhook request (signature!) and turn it into a PaymentEvent.
     *
     * @throws InvalidWebhookException when the signature or body is not valid
     */
    public function parseWebhook(Request $request): PaymentEvent;

    /** Ask the provider for the payment's status. Null while it is still pending or unknown. */
    public function fetchStatus(Payment $payment): ?PaymentEvent;
}
