<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A pretend provider for development and demos. It sends the customer to a local page where
 * they can pay, decline or cancel, and reports back through a signed webhook, exactly like a real one.
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function createCheckout(Payment $payment, string $returnUrl): CheckoutSession
    {
        return new CheckoutSession(
            id: 'fake_chk_'.Str::lower((string) Str::ulid()),
            url: route('fake-gateway.show', $payment),
        );
    }

    public function parseWebhook(Request $request): PaymentEvent
    {
        $expected = hash_hmac('sha256', $request->getContent(), (string) config('payments.fake.secret'));

        throw_unless(hash_equals($expected, (string) $request->header('X-Fake-Signature')), InvalidWebhookException::class, 'Bad signature.');

        $data = json_decode($request->getContent(), true);

        throw_if(! \is_array($data) || empty($data['checkout_id']) || ! \in_array($data['status'] ?? null, ['paid', 'failed'], true), InvalidWebhookException::class, 'Bad payload.');

        return new PaymentEvent($data['checkout_id'], $data['status'], $data['payment_id'] ?? null, $data['reason'] ?? null);
    }

    public function fetchStatus(Payment $payment): ?PaymentEvent
    {
        return null; // the fake gateway always reports through its webhook
    }
}
