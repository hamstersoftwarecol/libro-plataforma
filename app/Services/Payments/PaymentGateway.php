<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    /**
     * URL of the hosted checkout where the customer pays.
     */
    public function checkoutUrl(Payment $payment): string;

    /**
     * Verify the webhook signature and translate it into a gateway-agnostic event.
     *
     * @throws InvalidWebhookException
     */
    public function parseWebhook(Request $request): GatewayEvent;
}
