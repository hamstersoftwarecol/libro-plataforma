<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookStatus;
use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\ReadingProgress;
use App\Services\Finance\FinanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Admin overview (HU-022). Financial data is only built for users allowed to see it.
     */
    public function __invoke(Request $request): View
    {
        $catalog = [
            'Libros publicados' => Book::published()->count(),
            'Borradores' => Book::where('status', BookStatus::Draft)->count(),
            'Categorías' => Category::count(),
            'Autores' => Author::count(),
        ];

        if (! $request->user()->can('view-finances')) {
            return view('admin.dashboard', ['catalog' => $catalog]);
        }

        $currency = config('payments.currency');
        $thisMonth = FinanceReport::between(CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now());
        $lastTwelveMonths = FinanceReport::between(CarbonImmutable::now()->subMonths(11)->startOfMonth(), CarbonImmutable::now());

        return view('admin.dashboard', [
            'catalog' => $catalog,
            'currency' => $currency,
            'monthTotals' => $thisMonth->totalsByCurrency()[$currency] ?? ['income' => 0, 'expenses' => 0, 'net' => 0],
            'otherCurrencies' => collect($thisMonth->totalsByCurrency())->except($currency),
            'subscribers' => $thisMonth->subscriberStats(),
            'series' => $lastTwelveMonths->monthlySeries($currency),
            'activeReaders' => ReadingProgress::where('updated_at', '>=', now()->subDays(30))->distinct('user_id')->count('user_id'),
        ]);
    }
}
