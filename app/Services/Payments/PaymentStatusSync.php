<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Confirms pending payments by asking the gateway directly (server to server).
 */
class PaymentStatusSync
{
    public function __construct(private PaymentGatewayManager $gateways, private WebhookProcessor $processor) {}

    public function sync(Payment $payment): void
    {
        if (! $payment->isPending()) {
            return;
        }

        try {
            $gateway = $this->gateways->driver($payment->gateway);
        } catch (InvalidArgumentException) {
            return;
        }

        if (! $gateway instanceof SyncsPaymentStatus) {
            return;
        }

        // Avoid hammering the gateway when the result page auto-refreshes.
        if (! Cache::add('payment-sync:'.$payment->id, true, 3)) {
            return;
        }

        $event = $gateway->fetchStatus($payment);

        if ($event) {
            $this->processor->process($payment->gateway, $event);
            $payment->refresh();
        }
    }
}
