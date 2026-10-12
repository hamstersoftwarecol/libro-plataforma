<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiringMail;
use App\Models\Subscription;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('subscriptions:remind {--days=3 : Days before the end date}')]
#[Description('E-mail customers whose access ends soon (HU-032)')]
class SendExpiryReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;

        Subscription::query()
            ->grantingAccess()
            ->whereNull('expiry_reminder_sent_at')
            ->where('ends_at', '<=', now()->addDays((int) $this->option('days')))
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->with(['user', 'plan'])
            ->chunkById(100, function ($subscriptions) use (&$sent): void {
                foreach ($subscriptions as $subscription) {
                    Mail::to($subscription->user)->queue(new SubscriptionExpiringMail($subscription));
                    $subscription->forceFill(['expiry_reminder_sent_at' => now()])->saveQuietly();
                    $sent++;
                }
            });

        $this->info("Recordatorios enviados: {$sent}");

        return self::SUCCESS;
    }
}
