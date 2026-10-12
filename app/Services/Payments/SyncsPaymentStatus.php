<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Gateways that can be asked server-to-server for the current status of a payment.
 */
interface SyncsPaymentStatus
{
    /**
     * A verified event describing the final status, or null while it is still in progress.
     */
    public function fetchStatus(Payment $payment): ?GatewayEvent;
}
