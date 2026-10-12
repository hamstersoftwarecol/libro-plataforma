<?php

namespace App\Services\Payments;

use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionCancelledMail;
use App\Mail\SubscriptionPastDueMail;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Mail;

class SubscriptionService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Grant or extend access after a confirmed payment (HU-019, HU-021).
     * A renewal stacks on top of the remaining time of the current subscription.
     */
    public function activateFromPayment(Payment $payment): Subscription
    {
        $plan = $payment->plan;

        $subscription = Subscription::query()
            ->where('user_id', $payment->user_id)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Cancelled, SubscriptionStatus::PastDue])
            ->where('ends_at', '>', now()->subDays(3))
            ->latest('ends_at')
            ->lockForUpdate()
            ->first();

        $periodStart = $subscription && $subscription->ends_at->isFuture() ? $subscription->ends_at : now();

        if ($subscription) {
            $subscription->update([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'auto_renew' => true,
                'cancelled_at' => null,
                'ends_at' => $plan->interval->addTo($periodStart),
            ]);
            $action = 'subscription.renewed';
        } else {
            $subscription = Subscription::create([
                'user_id' => $payment->user_id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'auto_renew' => true,
                'starts_at' => now(),
                'ends_at' => $plan->interval->addTo(now()),
            ]);
            $action = 'subscription.started';
        }

        $payment->update(['subscription_id' => $subscription->id]);
        $this->auditLogger->log($action, $subscription, ['payment' => $payment->reference, 'ends_at' => $subscription->ends_at->toIso8601String()], $payment->user);

        return $subscription;
    }

    /**
     * Stop renewing; access continues until the paid period ends (HU-021).
     */
    public function cancel(Subscription $subscription): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'auto_renew' => false,
            'cancelled_at' => now(),
        ]);

        $this->auditLogger->log('subscription.cancelled', $subscription);
        Mail::to($subscription->user)->queue(new SubscriptionCancelledMail($subscription));
    }

    public function resume(Subscription $subscription): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'auto_renew' => true,
            'cancelled_at' => null,
        ]);

        $this->auditLogger->log('subscription.resumed', $subscription);
    }

    /**
     * Close subscriptions whose period ended (HU-019). Renewing ones without payment become past due.
     */
    public function expireEnded(): int
    {
        $becamePastDue = Subscription::query()
            ->with('user')
            ->where('status', SubscriptionStatus::Active)
            ->where('auto_renew', true)
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($becamePastDue as $subscription) {
            $subscription->update(['status' => SubscriptionStatus::PastDue]);

            if ($subscription->user->is_active) {
                Mail::to($subscription->user)->queue(new SubscriptionPastDueMail($subscription));
            }
        }

        $pastDue = $becamePastDue->count();

        $expired = Subscription::query()
            ->where(function ($query): void {
                $query->where('status', SubscriptionStatus::Cancelled)
                    ->orWhere(fn ($query) => $query->where('status', SubscriptionStatus::Active)->where('auto_renew', false))
                    ->orWhere(fn ($query) => $query->where('status', SubscriptionStatus::PastDue)->where('ends_at', '<=', now()->subDays(3)));
            })
            ->where('ends_at', '<=', now())
            ->update(['status' => SubscriptionStatus::Expired]);

        return $pastDue + $expired;
    }
}
