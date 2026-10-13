<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Book;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CheckoutService
{
    public function __construct(private PaymentGatewayManager $gateways, private AuditLogger $auditLogger) {}

    /**
     * Create a pending payment for the plan and return it with the hosted checkout URL (HU-018).
     * Guests may pay without an account: it is created once the payment is confirmed.
     *
     * Card details are never collected here: the gateway's hosted page handles them.
     *
     * @param  array{billing_name?: string, billing_document?: ?string, billing_country?: string, billing_phone?: ?string}  $billing
     */
    public function start(?User $user, string $email, Book $book, array $billing = []): CheckoutSession
    {
        if (! $book->isForSale()) {
            throw new RuntimeException('Este libro no está a la venta en este momento.');
        }

        $gateway = $this->gateways->driver();

        return DB::transaction(function () use ($gateway, $user, $email, $book, $billing): CheckoutSession {
            $payment = $this->createPayment($gateway, $user, mb_strtolower(trim($email)), $book, $billing);

            return new CheckoutSession($payment, $gateway->checkoutUrl($payment));
        });
    }

    /**
     * @param  array<string, ?string>  $billing
     */
    private function createPayment(PaymentGateway $gateway, ?User $user, string $email, Book $book, array $billing): Payment
    {
        $payment = Payment::create([
            'user_id' => $user?->id,
            'email' => $user?->email ?? $email,
            'book_id' => $book->id,
            'reference' => (string) Str::uuid(),
            'gateway' => $gateway->name(),
            'amount' => $book->price,
            'currency' => $book->currency,
            ...$billing,
            'terms_accepted_at' => $billing === [] ? null : now(),
            'status' => PaymentStatus::Pending,
        ]);

        if ($user && ! empty($billing['billing_name']) && ! $user->name) {
            $user->update(['name' => $billing['billing_name']]);
        }

        $this->auditLogger->log('payment.started', $payment, ['book' => $book->slug, 'amount' => $book->price, 'gateway' => $gateway->name()]);

        return $payment;
    }
}
