<?php

namespace Tests\Feature\Admin;

use App\Models\Book;
use App\Models\Category;
use App\Models\ReadingDailyStat;
use App\Models\ReadingProgress;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Analytics\ReadingAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Book $popular;

    private Book $niche;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));

        $this->popular = Book::factory()->published()->create(['title' => 'Libro popular', 'category_id' => Category::factory()->create(['name' => 'Novela'])->id]);
        $this->niche = Book::factory()->published()->create(['title' => 'Libro de nicho', 'category_id' => Category::factory()->create(['name' => 'Ensayo'])->id]);

        [$ana, $beto, $carla] = User::factory()->count(3)->create();
        $this->stat($ana, $this->popular, '2026-10-14', 20, 1800);
        $this->stat($beto, $this->popular, '2026-10-13', 10, 900);
        $this->stat($carla, $this->niche, '2026-10-12', 5, 300);
        $this->stat($carla, $this->niche, '2026-08-01', 50, 9000);

        ReadingProgress::create(['user_id' => $ana->id, 'book_id' => $this->popular->id, 'position' => 10, 'total' => 10, 'percentage' => 100, 'finished_at' => now()]);
        ReadingProgress::create(['user_id' => $beto->id, 'book_id' => $this->popular->id, 'position' => 3, 'total' => 10, 'percentage' => 30]);

        Subscription::factory()->for($ana)->create();
        Subscription::factory()->count(2)->create();
    }

    public function test_summary_for_the_last_30_days(): void
    {
        $summary = ReadingAnalytics::lastDays(30)->summary();

        $this->assertSame(3, $summary['readers']);
        $this->assertSame(3000, $summary['seconds']);
        $this->assertSame(35, $summary['pages']);
        $this->assertSame(1000, $summary['seconds_per_reader']);
        $this->assertSame(2, $summary['idle_subscribers']);
    }

    public function test_top_books_include_completion(): void
    {
        $top = ReadingAnalytics::lastDays(30)->topBooks();

        $this->assertSame('Libro popular', $top[0]['book']->title);
        $this->assertSame(2, $top[0]['readers']);
        $this->assertSame(2700, $top[0]['seconds']);
        $this->assertSame(2, $top[0]['started']);
        $this->assertSame(1, $top[0]['finished']);
        $this->assertSame(50.0, $top[0]['completion_rate']);
        $this->assertSame(65.0, $top[0]['average_progress']);
    }

    public function test_daily_series_fills_empty_days_and_categories_rank(): void
    {
        $analytics = ReadingAnalytics::lastDays(7);
        $series = $analytics->dailySeries();

        $this->assertCount(7, $series);
        $this->assertSame(1800, collect($series)->firstWhere(fn ($day) => $day['date']->toDateString() === '2026-10-14')['seconds']);
        $this->assertSame(0, end($series)['seconds']);
        $this->assertSame(['Novela', 'Ensayo'], $analytics->categories()->pluck('category')->all());
    }

    public function test_drop_off_counts_readers_reaching_each_point(): void
    {
        $dropOff = ReadingAnalytics::lastDays(30)->dropOff($this->popular);

        $this->assertSame(2, $dropOff['started']);
        $this->assertSame(1, $dropOff['finished']);
        $this->assertSame(2, $dropOff['bands']['≥ 30%']);
        $this->assertSame(1, $dropOff['bands']['≥ 40%']);
        $this->assertSame(1, $dropOff['bands']['≥ 100%']);
    }

    public function test_admin_and_editor_see_analytics_without_reader_identities(): void
    {
        $reader = User::where('role', 'reader')->first();

        foreach ([User::factory()->admin()->create(), User::factory()->editor()->create()] as $staff) {
            $this->actingAs($staff)->get(route('admin.analytics.index', ['days' => 30]))
                ->assertOk()
                ->assertSee('Libro popular')
                ->assertDontSee($reader->email);

            $this->actingAs($staff)->get(route('admin.analytics.show', [$this->popular, 'days' => 90]))
                ->assertOk()
                ->assertSee('Hasta dónde llegan los lectores');
        }
    }

    public function test_readers_cannot_see_analytics(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.analytics.index'))->assertForbidden();
    }

    public function test_invalid_range_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.analytics.index', ['days' => 3]))
            ->assertSessionHasErrors('days');
    }

    private function stat(User $user, Book $book, string $date, int $pages, int $seconds): void
    {
        ReadingDailyStat::create(['user_id' => $user->id, 'book_id' => $book->id, 'date' => $date, 'pages_viewed' => $pages, 'seconds_read' => $seconds]);
    }
}
