<?php

namespace App\Console\Commands;

use App\Services\Payments\SubscriptionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:expire')]
#[Description('Mark subscriptions whose paid period ended as past due or expired')]
class ExpireSubscriptions extends Command
{
    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expireEnded();

        $this->info("Suscripciones actualizadas: {$count}");

        return self::SUCCESS;
    }
}
