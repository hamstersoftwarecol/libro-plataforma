<?php

namespace App\Services\Analytics;

use App\Models\Book;
use App\Models\ReadingDailyStat;
use App\Models\ReadingProgress;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Aggregated, anonymous reading metrics for a date range (HU-033).
 */
class ReadingAnalytics
{
    public function __construct(public readonly CarbonImmutable $from, public readonly CarbonImmutable $to) {}

    public static function lastDays(int $days): self
    {
        return new self(CarbonImmutable::today()->subDays($days - 1), CarbonImmutable::today());
    }

    /**
     * @return array{readers: int, seconds: int, pages: int, books: int, seconds_per_reader: int, idle_subscribers: int}
     */
    public function summary(): array
    {
        $totals = $this->stats()->toBase()
            ->selectRaw('COUNT(DISTINCT user_id) as readers, COUNT(DISTINCT book_id) as books, COALESCE(SUM(seconds_read), 0) as seconds, COALESCE(SUM(pages_viewed), 0) as pages')
            ->first();

        $activeReaderIds = $this->stats()->distinct()->pluck('user_id');

        return [
            'readers' => (int) $totals->readers,
            'seconds' => (int) $totals->seconds,
            'pages' => (int) $totals->pages,
            'books' => (int) $totals->books,
            'seconds_per_reader' => $totals->readers ? intdiv((int) $totals->seconds, (int) $totals->readers) : 0,
            'idle_subscribers' => Subscription::query()->grantingAccess()->whereNotIn('user_id', $activeReaderIds)->distinct()->count('user_id'),
        ];
    }

    /**
     * One entry per day in the range, including days without activity.
     *
     * @return list<array{date: CarbonImmutable, seconds: int, readers: int}>
     */
    public function dailySeries(?Book $book = null): array
    {
        $rows = $this->stats($book)->toBase()
            ->selectRaw('date, SUM(seconds_read) as seconds, COUNT(DISTINCT user_id) as readers')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->date, 0, 10));

        $series = [];
        for ($day = $this->from; $day->lte($this->to); $day = $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $series[] = ['date' => $day, 'seconds' => (int) ($row->seconds ?? 0), 'readers' => (int) ($row->readers ?? 0)];
        }

        return $series;
    }

    /**
     * Reading time by day of the week, Monday first.
     *
     * @return array<int, int>
     */
    public function weekdayDistribution(): array
    {
        $distribution = array_fill(1, 7, 0);

        foreach (collect($this->dailySeries()) as $day) {
            $distribution[$day['date']->dayOfWeekIso] += $day['seconds'];
        }

        return $distribution;
    }

    /**
     * Most read books with engagement and completion.
     *
     * @return Collection<int, array{book: Book, readers: int, seconds: int, pages: int, started: int, finished: int, completion_rate: float, average_progress: float}>
     */
    public function topBooks(int $limit = 10): Collection
    {
        $rows = $this->stats()->toBase()
            ->selectRaw('book_id, COUNT(DISTINCT user_id) as readers, SUM(seconds_read) as seconds, SUM(pages_viewed) as pages')
            ->groupBy('book_id')
            ->orderByDesc('seconds')
            ->limit($limit)
            ->get();

        $books = Book::with('authors')->findMany($rows->pluck('book_id'))->keyBy('id');
        $completion = $this->completion($rows->pluck('book_id')->all());

        return $rows->filter(fn ($row) => $books->has($row->book_id))->map(fn ($row) => [
            'book' => $books[$row->book_id],
            'readers' => (int) $row->readers,
            'seconds' => (int) $row->seconds,
            'pages' => (int) $row->pages,
            ...$completion[$row->book_id] ?? ['started' => 0, 'finished' => 0, 'completion_rate' => 0.0, 'average_progress' => 0.0],
        ])->values();
    }

    /**
     * @return Collection<int, array{category: string, seconds: int, readers: int}>
     */
    public function categories(): Collection
    {
        return $this->stats()->toBase()
            ->join('books', 'books.id', '=', 'reading_daily_stats.book_id')
            ->leftJoin('categories', 'categories.id', '=', 'books.category_id')
            ->selectRaw('categories.name as category, SUM(reading_daily_stats.seconds_read) as seconds, COUNT(DISTINCT reading_daily_stats.user_id) as readers')
            ->groupBy('categories.name')
            ->orderByDesc('seconds')
            ->get()
            ->map(fn ($row) => ['category' => $row->category ?? 'Sin categoría', 'seconds' => (int) $row->seconds, 'readers' => (int) $row->readers]);
    }

    /**
     * Where readers stop: how many readers reached at least each 10% of the book (all time).
     *
     * @return array{bands: array<string, int>, started: int, finished: int}
     */
    public function dropOff(Book $book): array
    {
        $percentages = ReadingProgress::where('book_id', $book->id)->pluck('percentage');
        $bands = [];

        foreach (range(10, 100, 10) as $threshold) {
            $bands["≥ {$threshold}%"] = $percentages->filter(fn (float $percentage) => $percentage >= $threshold)->count();
        }

        return [
            'bands' => $bands,
            'started' => $percentages->count(),
            'finished' => ReadingProgress::where('book_id', $book->id)->whereNotNull('finished_at')->count(),
        ];
    }

    /**
     * @return array{readers: int, seconds: int, pages: int}
     */
    public function bookTotals(Book $book): array
    {
        $row = $this->stats($book)->toBase()
            ->selectRaw('COUNT(DISTINCT user_id) as readers, COALESCE(SUM(seconds_read), 0) as seconds, COALESCE(SUM(pages_viewed), 0) as pages')
            ->first();

        return ['readers' => (int) $row->readers, 'seconds' => (int) $row->seconds, 'pages' => (int) $row->pages];
    }

    /**
     * @param  list<int>  $bookIds
     * @return array<int, array{started: int, finished: int, completion_rate: float, average_progress: float}>
     */
    private function completion(array $bookIds): array
    {
        return ReadingProgress::query()
            ->whereIn('book_id', $bookIds)
            ->toBase()
            ->selectRaw('book_id, COUNT(*) as started, COUNT(finished_at) as finished, AVG(percentage) as average_progress')
            ->groupBy('book_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->book_id => [
                'started' => (int) $row->started,
                'finished' => (int) $row->finished,
                'completion_rate' => $row->started ? round($row->finished / $row->started * 100, 1) : 0.0,
                'average_progress' => round((float) $row->average_progress, 1),
            ]])
            ->all();
    }

    /**
     * @return Builder<ReadingDailyStat>
     */
    private function stats(?Book $book = null): Builder
    {
        return ReadingDailyStat::query()
            ->whereDate('date', '>=', $this->from->toDateString())
            ->whereDate('date', '<=', $this->to->toDateString())
            ->when($book, fn (Builder $query) => $query->where('book_id', $book->id));
    }

    public static function duration(int $seconds): string
    {
        if ($seconds < 3600) {
            return intdiv($seconds, 60).' min';
        }

        return intdiv($seconds, 3600).' h '.str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT).' min';
    }
}
