<?php

namespace App\Services\Finance;

use App\Enums\PaymentStatus;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Income, expenses and subscriber metrics for a date range (HU-022, HU-025, HU-026).
 *
 * Amounts are grouped by currency and never converted between currencies.
 */
class FinanceReport
{
    public function __construct(public readonly CarbonImmutable $from, public readonly CarbonImmutable $to) {}

    public static function between(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($from->startOfDay(), $to->endOfDay());
    }

    /**
     * @return array<string, array{income: int, expenses: int, net: int}>
     */
    public function totalsByCurrency(): array
    {
        $income = $this->paidPayments()->toBase()->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->pluck('total', 'currency');
        $expenses = $this->expenses()->toBase()->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->pluck('total', 'currency');

        return $income->keys()->merge($expenses->keys())->unique()->sort()->values()
            ->mapWithKeys(fn (string $currency) => [$currency => [
                'income' => (int) ($income[$currency] ?? 0),
                'expenses' => (int) ($expenses[$currency] ?? 0),
                'net' => (int) ($income[$currency] ?? 0) - (int) ($expenses[$currency] ?? 0),
            ]])
            ->all();
    }

    /**
     * @return Collection<string, int>
     */
    public function expensesByCategory(string $currency): Collection
    {
        return $this->expenses()
            ->where('currency', $currency)
            ->toBase()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->pluck('total', 'category')
            ->map(fn ($total) => (int) $total);
    }

    /**
     * Monthly income and expenses for a currency, oldest first.
     *
     * @return list<array{month: CarbonImmutable, income: int, expenses: int}>
     */
    public function monthlySeries(string $currency): array
    {
        $payments = $this->paidPayments()->where('currency', $currency)->get(['amount', 'paid_at']);
        $expenses = $this->expenses()->where('currency', $currency)->get(['amount', 'spent_on']);

        $series = [];
        for ($month = $this->from->startOfMonth(); $month->lte($this->to); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $series[$key] = [
                'month' => $month,
                'income' => (int) $payments->filter(fn (Payment $payment) => $payment->paid_at->format('Y-m') === $key)->sum('amount'),
                'expenses' => (int) $expenses->filter(fn (Expense $expense) => $expense->spent_on->format('Y-m') === $key)->sum('amount'),
            ];
        }

        return array_values($series);
    }

    /**
     * @return array{active: int, new: int, cancelled: int, past_due: int, mrr: array<string, int>}
     */
    public function subscriberStats(): array
    {
        $active = Subscription::query()->grantingAccess()->with('plan')->get();

        return [
            'active' => $active->unique('user_id')->count(),
            'new' => Subscription::query()->whereBetween('created_at', [$this->from, $this->to])->count(),
            'cancelled' => Subscription::query()->whereBetween('cancelled_at', [$this->from, $this->to])->count(),
            'past_due' => Subscription::query()->where('status', SubscriptionStatus::PastDue)->count(),
            'mrr' => $active->where('status', SubscriptionStatus::Active)->filter->plan
                ->groupBy('plan.currency')
                ->map(fn (Collection $subscriptions) => (int) round($subscriptions->sum(
                    fn (Subscription $subscription) => $subscription->plan->interval === PlanInterval::Yearly ? $subscription->plan->price / 12 : $subscription->plan->price
                )))
                ->all(),
        ];
    }

    /**
     * Income and expense movements merged and sorted by date, newest first.
     *
     * @return Collection<int, array{date: CarbonImmutable, type: string, concept: string, category: string, counterpart: string, amount: int, currency: string, reference: string}>
     */
    public function movements(): Collection
    {
        return $this->movementRows()->collect();
    }

    /**
     * Same movements, streamed for exports.
     *
     * @return LazyCollection<int, array{date: CarbonImmutable, type: string, concept: string, category: string, counterpart: string, amount: int, currency: string, reference: string}>
     */
    public function movementRows(): LazyCollection
    {
        return LazyCollection::make(function () {
            $income = $this->paidPayments()->with(['user', 'plan', 'book'])->get()->map(fn (Payment $payment) => [
                'date' => $payment->paid_at->toImmutable(),
                'type' => 'Ingreso',
                'concept' => $payment->book_id ? 'Venta '.$payment->description() : 'Suscripción '.$payment->plan?->name,
                'category' => $payment->book_id ? 'Venta de libros' : 'Suscripciones',
                'counterpart' => $payment->user?->email ?? (string) $payment->email,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reference' => $payment->reference,
                'responsible' => '',
            ]);

            $expenses = $this->expenses()->with('creator')->get()->map(fn (Expense $expense) => [
                'date' => $expense->spent_on->toImmutable(),
                'type' => 'Gasto',
                'concept' => $expense->description,
                'category' => $expense->category->label(),
                'counterpart' => (string) $expense->supplier,
                'amount' => -$expense->amount,
                'currency' => $expense->currency,
                'reference' => 'G-'.$expense->id,
                'responsible' => (string) $expense->creator?->email,
            ]);

            yield from $income->concat($expenses)->sortByDesc(fn (array $row) => $row['date']->timestamp)->values();
        });
    }

    public static function format(int $amount, string $currency): string
    {
        return Money::format($amount, $currency);
    }

    /**
     * @return Builder<Payment>
     */
    private function paidPayments(): Builder
    {
        return Payment::query()->where('status', PaymentStatus::Paid)->whereBetween('paid_at', [$this->from, $this->to]);
    }

    /**
     * @return Builder<Expense>
     */
    private function expenses(): Builder
    {
        return Expense::query()
            ->whereDate('spent_on', '>=', $this->from->toDateString())
            ->whereDate('spent_on', '<=', $this->to->toDateString());
    }
}
