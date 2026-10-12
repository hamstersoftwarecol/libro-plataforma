<?php

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Book;
use App\Models\Payment;
use App\Models\ReadingSession;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_lists_and_filters_customers(): void
    {
        $subscribed = Subscription::factory()->create()->user;
        $without = User::factory()->create();
        $staff = User::factory()->editor()->create();

        $this->actingAs($this->admin)->get(route('admin.customers.index'))
            ->assertOk()->assertSee($subscribed->email)->assertSee($without->email)->assertDontSee($staff->email);

        $this->actingAs($this->admin)->get(route('admin.customers.index', ['status' => 'subscribed']))
            ->assertSee($subscribed->email)->assertDontSee($without->email);

        $this->actingAs($this->admin)->get(route('admin.customers.index', ['status' => 'without']))
            ->assertSee($without->email)->assertDontSee($subscribed->email);
    }

    public function test_customer_detail_shows_subscription_and_payments(): void
    {
        $payment = Payment::factory()->paid()->create();

        $this->actingAs($this->admin)->get(route('admin.customers.show', $payment->user))
            ->assertOk()->assertSee($payment->reference)->assertSee('Pagado');
    }

    public function test_blocking_a_customer_ends_their_sessions(): void
    {
        $customer = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $customer->id, 'payload' => '', 'last_activity' => time()]);
        $readingSession = ReadingSession::create(['user_id' => $customer->id, 'book_id' => Book::factory()->create()->id, 'token' => 't', 'last_seen_at' => now()]);

        $this->actingAs($this->admin)->post(route('admin.customers.toggle-active', $customer));

        $this->assertFalse($customer->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);
        $this->assertNotNull($readingSession->fresh()->revoked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.deactivated']);

        $this->actingAs($this->admin)->post(route('admin.customers.toggle-active', $customer));
        $this->assertTrue($customer->fresh()->is_active);
    }

    public function test_courtesy_days_extend_or_create_access(): void
    {
        $this->freezeSecond();
        $subscription = Subscription::factory()->create(['ends_at' => now()->addDays(10)]);
        $without = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.customers.extend', $subscription->user), ['days' => 5, 'reason' => 'Compensación']);
        $this->assertTrue($subscription->fresh()->ends_at->eq(now()->addDays(15)));

        $this->actingAs($this->admin)->post(route('admin.customers.extend', $without), ['days' => 7, 'reason' => 'Prueba']);
        $this->assertTrue($without->hasActiveSubscription());
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.courtesy_extended']);
    }

    public function test_admin_can_cancel_and_revoke_access(): void
    {
        $subscription = Subscription::factory()->create();
        $customer = $subscription->user;

        $this->actingAs($this->admin)->post(route('admin.customers.cancel', $customer));
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->fresh()->status);
        $this->assertTrue($customer->hasActiveSubscription());

        $this->actingAs($this->admin)->post(route('admin.customers.revoke', $customer));
        $this->assertFalse($customer->hasActiveSubscription());
    }

    public function test_staff_accounts_cannot_be_managed_as_customers(): void
    {
        $this->actingAs($this->admin)->get(route('admin.customers.show', User::factory()->editor()->create()))->assertNotFound();
    }

    public function test_editors_cannot_access_customers(): void
    {
        $customer = User::factory()->create();
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('admin.customers.index'))->assertForbidden();
        $this->actingAs($editor)->post(route('admin.customers.toggle-active', $customer))->assertForbidden();
    }
}
