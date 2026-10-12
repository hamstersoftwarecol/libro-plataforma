<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(private PaymentGatewayManager $gateways, private AuditLogger $auditLogger) {}

    /**
     * Create a pending payment for the plan and return the hosted checkout URL (HU-018).
     *
     * Card details are never collected here: the gateway's hosted page handles them.
     *
     * @param  array{billing_name?: string, billing_document?: ?string, billing_country?: string, billing_phone?: ?string}  $billing
     */
    public function start(User $user, Plan $plan, array $billing = []): string
    {
        $gateway = $this->gateways->driver();

        return DB::transaction(function () use ($gateway, $user, $plan, $billing): string {
            $payment = $this->createPayment($gateway, $user, $plan, $billing);

            return $gateway->checkoutUrl($payment);
        });
    }

    /**
     * @param  array<string, ?string>  $billing
     */
    private function createPayment(PaymentGateway $gateway, User $user, Plan $plan, array $billing): Payment
    {
        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'reference' => (string) Str::uuid(),
            'gateway' => $gateway->name(),
            'amount' => $plan->price,
            'currency' => $plan->currency,
            ...$billing,
            'terms_accepted_at' => $billing === [] ? null : now(),
            'status' => PaymentStatus::Pending,
        ]);

        if (! empty($billing['billing_name']) && ! $user->name) {
            $user->update(['name' => $billing['billing_name']]);
        }

        $this->auditLogger->log('payment.started', $payment, ['plan' => $plan->slug, 'amount' => $plan->price, 'gateway' => $gateway->name()]);

        return $payment;
    }
}
