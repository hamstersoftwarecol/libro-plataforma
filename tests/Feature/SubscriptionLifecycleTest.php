<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Book;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_and_yearly_plans_set_the_right_period(): void
    {
        $this->freezeSecond();

        $monthly = $this->activate(Plan::factory()->create());
        $this->assertTrue($monthly->ends_at->eq(now()->addMonthNoOverflow()));

        $yearly = $this->activate(Plan::factory()->yearly()->create());
        $this->assertTrue($yearly->ends_at->eq(now()->addYearNoOverflow()));
    }

    public function test_renewal_stacks_on_top_of_remaining_time(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $plan = Plan::factory()->create();

        $first = $this->activate($plan, $user);
        $endsAt = $first->ends_at->copy();
        $second = $this->activate($plan, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->ends_at->eq($endsAt->addMonthNoOverflow()));
    }

    public function test_cancel_keeps_access_until_period_end(): void
    {
        $subscription = $this->activate(Plan::factory()->create());
        $user = $subscription->user;

        $this->actingAs($user)->post(route('account.subscription.cancel'))->assertSessionHas('status');

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertFalse($subscription->auto_renew);
        $this->assertTrue($user->hasActiveSubscription());

        $this->actingAs($user)->post(route('account.subscription.resume'));
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
    }

    public function test_expire_command_closes_ended_subscriptions(): void
    {
        $renewing = Subscription::factory()->create(['ends_at' => now()->subHour()]);
        $cancelled = Subscription::factory()->create(['status' => SubscriptionStatus::Cancelled, 'ends_at' => now()->subHour()]);
        $current = Subscription::factory()->create();

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::PastDue, $renewing->fresh()->status);
        $this->assertSame(SubscriptionStatus::Expired, $cancelled->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $current->fresh()->status);
        $this->assertFalse($renewing->user->hasActiveSubscription());

        $this->travel(4)->days();
        $this->artisan('subscriptions:expire');
        $this->assertSame(SubscriptionStatus::Expired, $renewing->fresh()->status);
    }

    public function test_paying_a_past_due_subscription_reactivates_it(): void
    {
        $this->freezeSecond();
        $subscription = Subscription::factory()->create(['status' => SubscriptionStatus::PastDue, 'ends_at' => now()->subDay()]);

        $renewed = $this->activate(Plan::factory()->create(), $subscription->user);

        $this->assertSame($subscription->id, $renewed->id);
        $this->assertSame(SubscriptionStatus::Active, $renewed->status);
        $this->assertTrue($renewed->ends_at->eq(now()->addMonthNoOverflow()));
    }

    public function test_account_page_lists_owned_books_and_payment_history(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->published()->create(['title' => 'Mi libro comprado']);
        $payment = Payment::factory()->paid()->create(['user_id' => $user->id, 'book_id' => $book->id, 'plan_id' => null]);
        $user->books()->attach($book->id, ['payment_id' => $payment->id, 'granted_at' => now()]);
        $otherPayment = Payment::factory()->paid()->create();

        $this->actingAs($user)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('Mis libros')
            ->assertSee('Mi libro comprado')
            ->assertSee('Acceso de por vida')
            ->assertSee('Pagado')
            ->assertDontSee(substr($otherPayment->reference, 0, 8));
    }

    private function activate(Plan $plan, ?User $user = null): Subscription
    {
        $payment = Payment::factory()->paid()->create(['plan_id' => $plan->id, 'user_id' => ($user ?? User::factory()->create())->id]);

        return app(SubscriptionService::class)->activateFromPayment($payment)->fresh();
    }
}
