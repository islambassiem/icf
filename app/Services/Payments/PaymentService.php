<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatusEnum;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\EnumPick;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * All the rules for buying a plan. It talks to the provider only through PaymentGateway,
 * so switching provider never touches this class.
 */
class PaymentService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * Create (or reuse) a pending subscription + payment and open a hosted checkout.
     * Nothing is active until the webhook confirms the payment.
     *
     * @return array{0: Payment, 1: CheckoutSession}
     */
    public function startCheckout(User $user, SubscriptionPlan $plan): array
    {
        $pending = EnumPick::case(PaymentStatusEnum::class, 'pending');

        $payment = DB::transaction(function () use ($user, $plan, $pending): Payment {
            // Clicking "pay" twice must not create two pending payments.
            $existing = Payment::query()
                ->where('status', $pending)
                ->where('created_at', '>=', now()->subMinutes((int) config('payments.pending_ttl_minutes')))
                ->whereHas('subscription', fn ($q) => $q
                    ->where('user_id', $user->id)
                    ->where('subscription_plan_id', $plan->id)
                    ->where('status', Subscription::STATUS_PENDING))
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $start = $this->nextStart($user);

            $subscription = Subscription::query()->create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'status' => Subscription::STATUS_PENDING,
                'starts_at' => $start,
                'ends_at' => $start->addDays($plan->duration_days),
                'amount_paid' => 0,
                'currency' => $plan->currency,
            ]);

            // The price always comes from the plan on the server, never from the browser.
            return $subscription->payments()->create([
                'provider' => $this->gateway->name(),
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'status' => $pending,
                'paid_at' => null,
            ]);
        });

        try {
            $session = $this->gateway->createCheckout($payment, route('subscription.return', $payment));
        } catch (Throwable $throwable) {
            $this->markFailed($payment, 'gateway_error');
            throw $throwable;
        }

        $payment->update(['provider' => $this->gateway->name(), 'provider_checkout_id' => $session->id]);

        return [$payment, $session];
    }

    /**
     * Apply a provider result. Safe to call many times for the same event (providers resend webhooks).
     */
    public function applyEvent(PaymentEvent $event): ?Payment
    {
        return DB::transaction(function () use ($event): ?Payment {
            $payment = Payment::query()->where('provider_checkout_id', $event->checkoutId)->lockForUpdate()->first();

            if (! $payment) {
                Log::warning('Payment event for an unknown checkout.', ['checkout_id' => $event->checkoutId]);

                return null;
            }

            $current = $payment->statusKey();

            // Money already received (or returned): never change it again.
            if (in_array($current, ['paid', 'refunded'], true)) {
                return $payment;
            }

            if ($event->isPaid()) {
                $this->markPaid($payment, $event);
            } elseif ($current === 'pending') {
                $this->markFailed($payment, $event->reason ?? 'declined', $event->providerPaymentId);
            }

            return $payment->refresh();
        });
    }

    /** When a newly bought period begins: right now, or when the running plan ends. */
    public function nextStart(User $user, ?int $exceptSubscriptionId = null): CarbonImmutable
    {
        $end = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->when($exceptSubscriptionId, fn ($q, $id) => $q->whereKeyNot($id))
            ->max('ends_at');

        return $end ? CarbonImmutable::parse($end) : CarbonImmutable::now();
    }

    private function markPaid(Payment $payment, PaymentEvent $event): void
    {
        $subscription = $payment->subscription()->with('subscriptionPlan')->lockForUpdate()->firstOrFail();

        // Work out the period now, in case the customer paid late or bought another plan meanwhile.
        $start = $this->nextStart($subscription->user, $subscription->id);

        $payment->update([
            'status' => EnumPick::case(PaymentStatusEnum::class, 'paid'),
            'provider_payment_id' => $event->providerPaymentId ?? $payment->provider_payment_id,
            'paid_at' => now(),
            'failure_reason' => null,
        ]);

        $subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $start,
            'ends_at' => $start->addDays($subscription->subscriptionPlan->duration_days),
            'amount_paid' => $payment->amount,
        ]);
    }

    private function markFailed(Payment $payment, string $reason, ?string $providerPaymentId = null): void
    {
        $payment->update([
            'status' => EnumPick::case(PaymentStatusEnum::class, 'failed'),
            'provider_payment_id' => $providerPaymentId ?? $payment->provider_payment_id,
            'failure_reason' => $reason,
        ]);

        $payment->subscription()->where('status', Subscription::STATUS_PENDING)->update(['status' => Subscription::STATUS_FAILED]);
    }
}
