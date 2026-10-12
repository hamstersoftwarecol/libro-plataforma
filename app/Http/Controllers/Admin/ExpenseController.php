<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExpenseRequest;
use App\Models\Expense;
use App\Services\AuditLogger;
use App\Services\Payments\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::enum(ExpenseCategory::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Expense::query()
            ->with('creator')
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('spent_on', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('spent_on', '<=', $to));

        return view('admin.expenses.index', [
            'expenses' => (clone $query)->latest('spent_on')->latest('id')->paginate(25)->withQueryString(),
            'totals' => (clone $query)->toBase()->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->pluck('total', 'currency'),
            'filters' => $filters,
            'categories' => ExpenseCategory::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.expenses.form', [
            'expense' => new Expense(['currency' => config('payments.currency'), 'spent_on' => today()]),
            'categories' => ExpenseCategory::cases(),
        ]);
    }

    /**
     * Record an expense with an optional receipt (HU-024).
     */
    public function store(ExpenseRequest $request): RedirectResponse
    {
        $expense = new Expense;
        $expense->created_by = $request->user()->id;
        $this->save($expense, $request);

        $this->auditLogger->log('expense.created', $expense, ['amount' => $expense->amount, 'currency' => $expense->currency]);

        return redirect()->route('admin.expenses.index')->with('status', 'Gasto registrado.');
    }

    public function edit(Expense $expense): View
    {
        return view('admin.expenses.form', ['expense' => $expense, 'categories' => ExpenseCategory::cases()]);
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->save($expense, $request);

        $this->auditLogger->log('expense.updated', $expense, ['changes' => array_keys($expense->getChanges())]);

        return redirect()->route('admin.expenses.index')->with('status', 'Gasto actualizado.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        if ($expense->receipt_path) {
            Storage::disk(Expense::RECEIPT_DISK)->delete($expense->receipt_path);
        }

        $this->auditLogger->log('expense.deleted', $expense, ['description' => $expense->description, 'amount' => $expense->amount]);
        $expense->delete();

        return redirect()->route('admin.expenses.index')->with('status', 'Gasto eliminado.');
    }

    /**
     * Download the receipt from private storage.
     */
    public function receipt(Expense $expense): StreamedResponse
    {
        abort_unless($expense->receipt_path && Storage::disk(Expense::RECEIPT_DISK)->exists($expense->receipt_path), 404);

        return Storage::disk(Expense::RECEIPT_DISK)->download($expense->receipt_path, $expense->receipt_name);
    }

    private function save(Expense $expense, ExpenseRequest $request): void
    {
        $data = $request->safe()->except('receipt');
        $data['currency'] = strtoupper($data['currency']);
        $data['amount'] = Money::toMinor($data['amount'], $data['currency']);

        $expense->fill($data);

        if ($request->hasFile('receipt')) {
            $previous = $expense->receipt_path;
            $expense->receipt_path = $request->file('receipt')->store('receipts', Expense::RECEIPT_DISK);
            $expense->receipt_name = mb_substr($request->file('receipt')->getClientOriginalName(), 0, 255);

            if ($previous) {
                Storage::disk(Expense::RECEIPT_DISK)->delete($previous);
            }
        }

        $expense->save();
    }
}
