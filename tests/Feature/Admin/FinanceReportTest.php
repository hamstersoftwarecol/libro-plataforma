<?php

namespace Tests\Feature\Admin;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        $this->admin = User::factory()->admin()->create();

        Payment::factory()->paid()->create(['amount' => 5000, 'currency' => 'USD', 'paid_at' => '2026-10-05 10:00:00']);
        Payment::factory()->paid()->create(['amount' => 3000, 'currency' => 'USD', 'paid_at' => '2026-09-20 10:00:00']);
        Payment::factory()->paid()->create(['amount' => 40000, 'currency' => 'COP', 'paid_at' => '2026-10-06 10:00:00']);
        Payment::factory()->create(['amount' => 7777, 'currency' => 'USD']);
        Expense::factory()->create(['description' => 'Servidor', 'category' => 'hosting', 'amount' => 1200, 'currency' => 'USD', 'spent_on' => '2026-10-01']);
        Expense::factory()->create(['description' => '=HYPERLINK("http://malo")', 'category' => 'other', 'amount' => 800, 'currency' => 'USD', 'spent_on' => '2026-09-02']);
    }

    public function test_this_month_totals_by_currency(): void
    {
        $this->actingAs($this->admin)->get(route('admin.finances.index'))
            ->assertOk()
            ->assertViewHas('totals', [
                'COP' => ['income' => 40000, 'expenses' => 0, 'net' => 40000],
                'USD' => ['income' => 5000, 'expenses' => 1200, 'net' => 3800],
            ])
            ->assertSee('Servidores y hosting');
    }

    public function test_previous_month_and_custom_ranges(): void
    {
        $this->actingAs($this->admin)->get(route('admin.finances.index', ['period' => 'last_month']))
            ->assertViewHas('totals', ['USD' => ['income' => 3000, 'expenses' => 800, 'net' => 2200]]);

        $this->actingAs($this->admin)->get(route('admin.finances.index', ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-10-31']))
            ->assertViewHas('totals', fn (array $totals) => $totals['USD']['income'] === 8000 && $totals['USD']['net'] === 6000);
    }

    public function test_custom_range_requires_valid_dates(): void
    {
        $this->actingAs($this->admin)->get(route('admin.finances.index', ['period' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_export_streams_csv_with_all_movements(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.finances.export', ['period' => 'this_year']));

        $response->assertOk()->assertDownload('movimientos_20260101_20261015.csv');
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Fecha;Tipo;Concepto', $csv);
        $this->assertStringContainsString('Ingreso', $csv);
        $this->assertStringContainsString('50,00;USD', $csv);
        $this->assertStringContainsString('-12,00;USD', $csv);
        $this->assertStringContainsString(';40000;COP', $csv);
        $this->assertStringNotContainsString('77,77', $csv);
        $this->assertStringContainsString('\'=HYPERLINK', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.exported']);
    }

    public function test_editors_cannot_see_finances(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('admin.finances.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('admin.finances.export'))->assertForbidden();
    }
}
