<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Services\Payments\InvalidWebhookException;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The provider calls this directly (no login, no CSRF). It is the only place that activates a plan.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, PaymentService $payments): JsonResponse
    {
        try {
            $event = $gateway->parseWebhook($request);
        } catch (InvalidWebhookException) {
            return response()->json(['error' => 'invalid'], 400);
        }

        // Always answer 200 for a valid event, even if we do not know the payment, so the provider stops retrying.
        $payments->applyEvent($event);

        return response()->json(['ok' => true]);
    }
}
