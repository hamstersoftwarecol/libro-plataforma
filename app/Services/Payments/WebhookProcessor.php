<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\PaymentFailedMail;
use App\Mail\PaymentReceiptMail;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Applies verified gateway events exactly once (HU-018, HU-030).
 */
class WebhookProcessor
{
    public const PROCESSED = 'processed';

    public const DUPLICATE = 'duplicate';

    public const IGNORED = 'ignored';

    public function __construct(
        private PaymentGatewayManager $gateways,
        private SubscriptionService $subscriptions,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @throws InvalidWebhookException
     */
    public function handle(string $gatewayName, Request $request): string
    {
        return $this->process($gatewayName, $this->gateways->driver($gatewayName)->parseWebhook($request));
    }

    /**
     * Apply an already verified event (from a signed webhook or a server-to-server status query).
     */
    public function process(string $gatewayName, GatewayEvent $event): string
    {
        return DB::transaction(function () use ($gatewayName, $event): string {
            try {
                $record = WebhookEvent::create([
                    'gateway' => $gatewayName,
                    'event_id' => $event->id,
                    'type' => $event->type,
                    'payload' => $event->payload,
                ]);
            } catch (UniqueConstraintViolationException) {
                return self::DUPLICATE;
            }

            $payment = Payment::query()
                ->where('reference', $event->reference)
                ->where('gateway', $gatewayName)
                ->lockForUpdate()
                ->first();

            $result = $payment ? $this->apply($payment, $event) : self::IGNORED;

            $record->update(['processed_at' => now()]);

            return $result;
        });
    }

    private function apply(Payment $payment, GatewayEvent $event): string
    {
        if ($event->amount !== $payment->amount || strtoupper($event->currency) !== strtoupper($payment->currency)) {
            $this->auditLogger->log('payment.amount_mismatch', $payment, ['event' => $event->id, 'amount' => $event->amount, 'currency' => $event->currency], $payment->user);

            return self::IGNORED;
        }

        if (! $payment->isPending()) {
            return self::IGNORED;
        }

        if ($event->type === GatewayEvent::PAYMENT_SUCCEEDED) {
            $payment->update([
                'status' => PaymentStatus::Paid,
                'gateway_payment_id' => $event->gatewayPaymentId,
                'paid_at' => now(),
            ]);
            $this->auditLogger->log('payment.paid', $payment, ['event' => $event->id], $payment->user);
            $this->subscriptions->activateFromPayment($payment);
            Mail::to($payment->user)->queue(new PaymentReceiptMail($payment->fresh(['plan', 'subscription'])));

            return self::PROCESSED;
        }

        if ($event->type === GatewayEvent::PAYMENT_FAILED) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'gateway_payment_id' => $event->gatewayPaymentId,
                'failure_reason' => mb_substr((string) $event->failureReason, 0, 255) ?: 'Pago rechazado',
            ]);
            $this->auditLogger->log('payment.failed', $payment, ['event' => $event->id, 'reason' => $event->failureReason], $payment->user);

            $payment->user->subscriptions()
                ->where('status', SubscriptionStatus::Active)
                ->where('ends_at', '<=', now())
                ->update(['status' => SubscriptionStatus::PastDue]);

            Mail::to($payment->user)->queue(new PaymentFailedMail($payment->fresh('plan')));

            return self::PROCESSED;
        }

        return self::IGNORED;
    }
}
