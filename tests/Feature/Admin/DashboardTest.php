<?php

namespace Tests\Feature\Admin;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_monthly_finances_and_sales(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        Payment::factory()->paid()->create(['amount' => 2000, 'currency' => 'USD']);
        Payment::factory()->paid()->create(['amount' => 1000, 'currency' => 'USD', 'paid_at' => now()->subMonths(2)]);
        Payment::factory()->create(['amount' => 99999, 'currency' => 'USD']);
        Expense::factory()->create(['amount' => 500, 'currency' => 'USD', 'spent_on' => now()]);
        Subscription::factory()->create(['plan_id' => Plan::factory()->yearly()->create(['price' => 12000])->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('monthTotals', ['income' => 2000, 'expenses' => 500, 'net' => 1500])
            ->assertViewHas('sales', fn (array $sales) => array_key_exists('month', $sales) && array_key_exists('buyers', $sales))
            ->assertViewHas('series', fn (array $series) => count($series) === 12 && $series[9]['income'] === 1000)
            ->assertSee('Resultado neto');
    }

    public function test_editor_dashboard_has_no_financial_data(): void
    {
        Payment::factory()->paid()->create();

        $this->actingAs(User::factory()->editor()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewMissing('monthTotals')
            ->assertDontSee('Ingresos')
            ->assertDontSee('Clientes');
    }
}
