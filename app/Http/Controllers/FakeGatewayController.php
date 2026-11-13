<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** The pretend provider's payment page. Only registered when PAYMENT_GATEWAY=fake outside production. */
class FakeGatewayController extends Controller
{
    public function show(Payment $payment): RedirectResponse|View
    {
        $this->authorizeOwner($payment);

        if ($payment->statusKey() !== 'pending') {
            return to_route('subscription.return', $payment);
        }

        return view('payments.fake-gateway', [
            'payment' => $payment->load('subscription.subscriptionPlan'),
        ]);
    }

    public function complete(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizeOwner($payment);

        $outcome = $request->validate([
            'outcome' => ['required', 'in:pay,declined,insufficient_funds,canceled'],
        ])['outcome'];

        // Report the result the way a real provider does: a signed webhook.
        $payload = json_encode([
            'checkout_id' => $payment->provider_checkout_id,
            'payment_id' => 'fake_pay_'.Str::lower(Str::random(12)),
            'status' => $outcome === 'pay' ? 'paid' : 'failed',
            'reason' => $outcome === 'pay' ? null : $outcome,
        ]);

        $payload = \is_string($payload) ? $payload : '';

        $webhook = Request::create(route('webhooks.payments'), 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => hash_hmac('sha256', $payload, (string) config('payments.fake.secret')),
        ], $payload);

        app()->call(resolve(PaymentWebhookController::class), ['request' => $webhook]);

        return to_route('subscription.return', $payment);
    }

    private function authorizeOwner(Payment $payment): void
    {
        abort_unless($payment->subscription?->user_id === auth()->id(), 404);
    }
}
