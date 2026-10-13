<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\SandboxGateway;
use App\Services\Payments\WebhookProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Simulated hosted checkout for local development. Disabled in production.
 */
class SandboxCheckoutController extends Controller
{
    public function show(Payment $payment): View
    {
        abort_unless($payment->gateway === 'sandbox' && config('payments.gateways.sandbox.enabled'), 404);

        return view('checkout.sandbox', ['payment' => $payment->load('plan')]);
    }

    /**
     * Simulate the customer's decision and the gateway's signed webhook.
     */
    public function complete(Request $request, Payment $payment, PaymentGatewayManager $gateways, WebhookProcessor $processor): RedirectResponse
    {
        abort_unless($payment->gateway === 'sandbox' && config('payments.gateways.sandbox.enabled'), 404);

        $approved = $request->validate(['outcome' => ['required', 'in:approve,decline']])['outcome'] === 'approve';

        /** @var SandboxGateway $gateway */
        $gateway = $gateways->driver('sandbox');
        $webhook = $gateway->signedWebhook([
            'id' => 'evt_'.Str::random(20),
            'type' => $approved ? GatewayEvent::PAYMENT_SUCCEEDED : GatewayEvent::PAYMENT_FAILED,
            'reference' => $payment->reference,
            'payment_id' => 'sbx_'.Str::random(16),
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'failure_reason' => $approved ? null : 'Tarjeta rechazada (simulación)',
        ]);

        $webhookRequest = Request::create(route('webhooks.payments', 'sandbox'), 'POST', server: collect($webhook['headers'])
            ->mapWithKeys(fn (string $value, string $name) => ['HTTP_'.strtoupper(str_replace('-', '_', $name)) => $value])
            ->put('CONTENT_TYPE', 'application/json')
            ->all(), content: $webhook['body']);

        $processor->handle('sandbox', $webhookRequest);

        return redirect()->route('checkout.show', $payment);
    }
}
