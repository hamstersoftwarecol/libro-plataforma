<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Finance\FinanceReport;
use App\Services\Payments\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public const PERIODS = [
        'this_month' => 'Este mes',
        'last_month' => 'Mes anterior',
        'this_year' => 'Este año',
        'last_12_months' => 'Últimos 12 meses',
        'custom' => 'Personalizado',
    ];

    /**
     * Income, expenses and net result for a period (HU-025, HU-026).
     */
    public function index(Request $request): View
    {
        [$report, $filters] = $this->report($request);
        $totals = $report->totalsByCurrency();

        return view('admin.finances.index', [
            'report' => $report,
            'filters' => $filters,
            'periods' => self::PERIODS,
            'totals' => $totals,
            'categoryBreakdown' => collect(array_keys($totals))->mapWithKeys(fn (string $currency) => [
                $currency => $report->expensesByCategory($currency)->mapWithKeys(fn (int $total, string $category) => [
                    ExpenseCategory::from($category)->label() => $total,
                ]),
            ]),
            'movements' => $report->movements()->take(200),
        ]);
    }

    /**
     * Export movements as CSV, readable by Excel (HU-027).
     */
    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        [$report] = $this->report($request);

        $auditLogger->log('finance.exported', metadata: ['from' => $report->from->toDateString(), 'to' => $report->to->toDateString()]);

        $filename = sprintf('movimientos_%s_%s.csv', $report->from->format('Ymd'), $report->to->format('Ymd'));

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Fecha', 'Tipo', 'Concepto', 'Categoría', 'Cliente / Proveedor', 'Monto', 'Moneda', 'Referencia', 'Responsable'], ';', escape: '');

            foreach ($report->movementRows() as $row) {
                fputcsv($output, [
                    $row['date']->format('Y-m-d'),
                    $row['type'],
                    $this->safeCell($row['concept']),
                    $row['category'],
                    $this->safeCell($row['counterpart']),
                    number_format(Money::toMajor($row['amount'], $row['currency']), Money::decimals($row['currency']), ',', ''),
                    $row['currency'],
                    $row['reference'],
                    $row['responsible'],
                ], ';', escape: '');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: FinanceReport, 1: array{period: string, from: string, to: string}}
     */
    private function report(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:'.implode(',', array_keys(self::PERIODS))],
            'from' => ['nullable', 'required_if:period,custom', 'date'],
            'to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:from'],
        ]);

        $period = $validated['period'] ?? 'this_month';
        $now = CarbonImmutable::now();

        [$from, $to] = match ($period) {
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->startOfYear(), $now],
            'last_12_months' => [$now->subMonths(11)->startOfMonth(), $now],
            'custom' => [CarbonImmutable::parse($validated['from']), CarbonImmutable::parse($validated['to'])],
            default => [$now->startOfMonth(), $now],
        };

        return [FinanceReport::between($from, $to), ['period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString()]];
    }

    /**
     * Prevent spreadsheet formula injection from user-controlled text.
     */
    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
