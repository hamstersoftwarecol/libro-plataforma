<?php

namespace Tests\Feature\Admin;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_records_an_expense_with_receipt(): void
    {
        $this->actingAs($this->admin)->post(route('admin.expenses.store'), [
            'category' => 'hosting',
            'description' => 'Servidor octubre',
            'amount' => '45.50',
            'currency' => 'usd',
            'spent_on' => today()->toDateString(),
            'supplier' => 'Proveedor Cloud',
            'receipt' => UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('admin.expenses.index'));

        $expense = Expense::sole();
        $this->assertSame(4550, $expense->amount);
        $this->assertSame('USD', $expense->currency);
        $this->assertSame(ExpenseCategory::Hosting, $expense->category);
        $this->assertSame($this->admin->id, $expense->created_by);
        Storage::disk('local')->assertExists($expense->receipt_path);
        Storage::disk('public')->assertMissing($expense->receipt_path);

        $this->actingAs($this->admin)->get(route('admin.expenses.index'))->assertSee($this->admin->displayName());

        $this->actingAs($this->admin)->get(route('admin.expenses.receipt', $expense))
            ->assertOk()->assertDownload('factura.pdf');
    }

    public function test_future_dates_and_bad_files_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.expenses.store'), [
            'category' => 'hosting', 'description' => 'X', 'amount' => '10', 'currency' => 'USD',
            'spent_on' => today()->addDay()->toDateString(),
            'receipt' => UploadedFile::fake()->create('script.php', 1, 'text/x-php'),
        ])->assertSessionHasErrors(['spent_on', 'receipt']);
    }

    public function test_updating_replaces_the_receipt(): void
    {
        $expense = Expense::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.expenses.update', $expense), [
            'category' => 'marketing', 'description' => 'Campaña', 'amount' => '100', 'currency' => 'USD',
            'spent_on' => today()->toDateString(),
            'receipt' => UploadedFile::fake()->image('recibo.jpg'),
        ])->assertSessionHasNoErrors();

        $first = $expense->fresh()->receipt_path;

        $this->actingAs($this->admin)->put(route('admin.expenses.update', $expense), [
            'category' => 'marketing', 'description' => 'Campaña', 'amount' => '100', 'currency' => 'USD',
            'spent_on' => today()->toDateString(),
            'receipt' => UploadedFile::fake()->image('recibo2.jpg'),
        ]);

        Storage::disk('local')->assertMissing($first);
        $this->assertSame(10000, $expense->fresh()->amount);
    }

    public function test_expenses_are_filtered_and_totalled(): void
    {
        Expense::factory()->create(['description' => 'Hosting viejo', 'category' => 'hosting', 'amount' => 1000, 'spent_on' => '2026-01-10']);
        Expense::factory()->create(['description' => 'Anuncios', 'category' => 'marketing', 'amount' => 2500, 'spent_on' => '2026-02-10']);

        $this->actingAs($this->admin)->get(route('admin.expenses.index', ['category' => 'marketing']))
            ->assertSee('Anuncios')->assertDontSee('Hosting viejo')->assertViewHas('totals', fn ($totals) => (int) $totals['USD'] === 2500);

        $this->actingAs($this->admin)->get(route('admin.expenses.index', ['from' => '2026-01-01', 'to' => '2026-01-31']))
            ->assertSee('Hosting viejo')->assertDontSee('Anuncios');
    }

    public function test_deleting_removes_the_receipt(): void
    {
        $this->actingAs($this->admin)->post(route('admin.expenses.store'), [
            'category' => 'other', 'description' => 'Temporal', 'amount' => '5', 'currency' => 'USD',
            'spent_on' => today()->toDateString(),
            'receipt' => UploadedFile::fake()->create('f.pdf', 10, 'application/pdf'),
        ]);
        $expense = Expense::sole();

        $this->actingAs($this->admin)->delete(route('admin.expenses.destroy', $expense));

        $this->assertModelMissing($expense);
        Storage::disk('local')->assertMissing($expense->receipt_path);
    }

    public function test_editors_cannot_manage_expenses(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('admin.expenses.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('admin.expenses.receipt', Expense::factory()->create()))->assertForbidden();
    }
}
