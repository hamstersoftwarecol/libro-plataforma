<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\Analytics\ReadingAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public const RANGES = [7 => 'Últimos 7 días', 30 => 'Últimos 30 días', 90 => 'Últimos 90 días', 365 => 'Último año'];

    /**
     * Reading analytics overview (HU-033). Aggregated data only, no reader identities.
     */
    public function index(Request $request): View
    {
        $days = $this->days($request);
        $analytics = ReadingAnalytics::lastDays($days);

        return view('admin.analytics.index', [
            'days' => $days,
            'ranges' => self::RANGES,
            'summary' => $analytics->summary(),
            'series' => $analytics->dailySeries(),
            'weekdays' => $analytics->weekdayDistribution(),
            'topBooks' => $analytics->topBooks(),
            'categories' => $analytics->categories(),
        ]);
    }

    public function show(Request $request, Book $book): View
    {
        $days = $this->days($request);
        $analytics = ReadingAnalytics::lastDays($days);

        return view('admin.analytics.book', [
            'book' => $book->load('authors'),
            'days' => $days,
            'ranges' => self::RANGES,
            'totals' => $analytics->bookTotals($book),
            'series' => $analytics->dailySeries($book),
            'dropOff' => $analytics->dropOff($book),
        ]);
    }

    private function days(Request $request): int
    {
        $validated = $request->validate(['days' => ['nullable', 'integer', 'in:'.implode(',', array_keys(self::RANGES))]]);

        return (int) ($validated['days'] ?? 30);
    }
}
