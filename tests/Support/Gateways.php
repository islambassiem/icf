<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\InvalidWebhookException;
use App\Services\Payments\PaymentEvent;
use Illuminate\Http\Request;
use RuntimeException;

/** Gateways that misbehave on purpose, for the sad-path tests. */
final class Gateways
{
    /** The provider is down: creating a checkout always fails. */
    public static function down(): PaymentGateway
    {
        return new class implements PaymentGateway
        {
            public function name(): string
            {
                return 'down';
            }

            public function createCheckout(Payment $payment, string $returnUrl): CheckoutSession
            {
                throw new RuntimeException('Provider is down.');
            }

            public function parseWebhook(Request $request): PaymentEvent
            {
                throw new InvalidWebhookException('Provider is down.');
            }

            public function fetchStatus(Payment $payment): ?PaymentEvent
            {
                return null;
            }
        };
    }

    /** The webhook never arrives, but asking the provider says the payment succeeded. */
    public static function lateWebhook(): PaymentGateway
    {
        return new class implements PaymentGateway
        {
            public function name(): string
            {
                return 'late';
            }

            public function createCheckout(Payment $payment, string $returnUrl): CheckoutSession
            {
                return new CheckoutSession('late_chk_'.$payment->id, 'https://provider.test/pay');
            }

            public function parseWebhook(Request $request): PaymentEvent
            {
                throw new InvalidWebhookException('No webhooks.');
            }

            public function fetchStatus(Payment $payment): PaymentEvent
            {
                return new PaymentEvent((string) $payment->provider_checkout_id, 'paid', 'late_pay_1');
            }
        };
    }
}
