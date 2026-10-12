<?php

namespace Database\Seeders;

use App\Enums\ExpenseCategory;
use App\Enums\SubscriptionStatus;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class FinanceDemoSeeder extends Seeder
{
    /**
     * Six months of demo customers, payments and expenses for the dashboard.
     */
    public function run(): void
    {
        $plans = Plan::all();

        for ($monthsAgo = 5; $monthsAgo >= 0; $monthsAgo--) {
            $month = now()->subMonthsNoOverflow($monthsAgo)->startOfMonth();

            foreach (range(1, 4 + (5 - $monthsAgo) * 2) as $index) {
                $plan = $plans->random();
                $paidAt = $month->copy()->addDays(random_int(0, min(27, now()->diffInDays($month, true))))->setTime(random_int(8, 22), random_int(0, 59));
                $customer = User::factory()->create(['created_at' => $paidAt, 'last_login_at' => $paidAt]);
                $endsAt = $plan->interval->addTo($paidAt);

                $subscription = Subscription::factory()->for($customer)->create([
                    'plan_id' => $plan->id,
                    'status' => $endsAt->isPast() ? SubscriptionStatus::Expired : SubscriptionStatus::Active,
                    'starts_at' => $paidAt,
                    'ends_at' => $endsAt,
                    'created_at' => $paidAt,
                ]);

                Payment::factory()->paid()->create([
                    'user_id' => $customer->id,
                    'plan_id' => $plan->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $plan->price,
                    'currency' => $plan->currency,
                    'paid_at' => $paidAt,
                    'created_at' => $paidAt,
                ]);
            }

            Expense::factory()->create(['category' => ExpenseCategory::Hosting, 'description' => 'Servidor y almacenamiento', 'amount' => 4500, 'spent_on' => $month->copy()->addDays(2)]);
            Expense::factory()->create(['category' => ExpenseCategory::Royalties, 'description' => 'Regalías editoriales', 'amount' => random_int(6000, 12000), 'spent_on' => $month->copy()->addDays(10)]);
            Expense::factory()->create(['category' => ExpenseCategory::Marketing, 'description' => 'Campaña en redes', 'amount' => random_int(2000, 8000), 'spent_on' => $month->copy()->addDays(15)->min(now())]);
        }
    }
}
