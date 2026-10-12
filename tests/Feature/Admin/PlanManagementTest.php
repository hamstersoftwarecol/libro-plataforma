<?php

namespace Tests\Feature\Admin;

use App\Enums\PlanInterval;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_and_updates_plans(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Anual', 'interval' => 'yearly', 'price' => '99.90', 'currency' => 'usd', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $plan = Plan::sole();
        $this->assertSame(9990, $plan->price);
        $this->assertSame('USD', $plan->currency);
        $this->assertSame(PlanInterval::Yearly, $plan->interval);

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => 'Anual', 'interval' => 'yearly', 'price' => '120000', 'currency' => 'COP', 'is_active' => '0',
        ])->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(120000, $plan->price);
        $this->assertFalse($plan->is_active);
    }

    public function test_editors_cannot_manage_plans_or_see_payments(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('admin.plans.index'))->assertForbidden();
        $this->actingAs($editor)->post(route('admin.plans.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($editor)->get(route('admin.payments.index'))->assertForbidden();
    }

    public function test_admin_sees_and_filters_payments(): void
    {
        $paid = Payment::factory()->paid()->create();
        $pending = Payment::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.payments.index', ['status' => 'paid']))
            ->assertOk()
            ->assertSee($paid->user->email)
            ->assertDontSee($pending->user->email);
    }
}
