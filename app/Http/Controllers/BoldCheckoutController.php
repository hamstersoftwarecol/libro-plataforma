<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Payment;
use App\Services\Payments\BoldGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentStatusSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoldCheckoutController extends Controller
{
    /**
     * Page with Bold's payment button, signed on the server.
     */
    public function show(Request $request, Payment $payment, PaymentGatewayManager $gateways): View|RedirectResponse
    {
        abort_unless($payment->belongsToVisitor($request) && $payment->gateway === 'bold', 404);

        if (! $payment->isPending()) {
            return redirect()->route('checkout.show', $payment);
        }

        /** @var BoldGateway $gateway */
        $gateway = $gateways->driver('bold');

        return view('checkout.bold', [
            'payment' => $payment->load('plan', 'book'),
            'book' => $payment->book ?? Book::featured(),
            'button' => [
                'api-key' => $gateway->identityKey(),
                'order-id' => $payment->reference,
                'currency' => strtoupper($payment->currency),
                'amount' => (string) $gateway->amountFor($payment),
                'integrity-signature' => $gateway->integritySignature($payment),
                'redirection-url' => route('checkout.bold.return'),
                'description' => mb_substr($payment->description(), 0, 100),
                'customer-data' => json_encode(array_filter([
                    'email' => $payment->email,
                    'fullName' => $payment->billing_name,
                    'phone' => $payment->billing_phone ? preg_replace('/\D+/', '', $payment->billing_phone) : null,
                    'documentNumber' => $payment->billing_document,
                ])),
                'billing-address' => json_encode(array_filter(['country' => $payment->billing_country])),
            ],
            'scriptUrl' => config('payments.gateways.bold.script_url'),
            'testMode' => $gateway->isTestMode(),
        ]);
    }

    /**
     * Bold sends the customer back here with ?bold-order-id=...&bold-tx-status=...
     * The status in the URL is never trusted: the server asks Bold directly.
     */
    public function return(Request $request, PaymentStatusSync $sync): RedirectResponse
    {
        $payment = Payment::query()
            ->where('reference', (string) $request->query('bold-order-id'))
            ->where('gateway', 'bold')
            ->firstOrFail();

        abort_unless($payment->belongsToVisitor($request), 404);

        $sync->sync($payment);

        return redirect()->route('checkout.show', $payment);
    }
}
