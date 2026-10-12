<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ReadingDailyStat;
use App\Models\ReadingSession;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BookFileStorage;
use App\Services\Reader\ReadingTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\BookFixtures;
use Tests\TestCase;

class ReadingTrackerTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;

    private Book $book;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('books');
        $this->freezeSecond();

        $this->reader = User::factory()->create();
        Subscription::factory()->for($this->reader)->create();
        $this->book = Book::factory()->published()->create();
        app(BookFileStorage::class)->storeBookFile($this->book, BookFixtures::pdfUpload(10));
        $this->book->refresh();

        $this->actingAs($this->reader)->get(route('reader.show', $this->book));
        $this->token = ReadingSession::latest('id')->value('token');
    }

    public function test_page_turns_and_time_are_aggregated_per_day(): void
    {
        $this->progress(1);
        $this->travel(40)->seconds();
        $this->progress(2);
        $this->travel(50)->seconds();
        $this->progress(3);

        $stat = ReadingDailyStat::sole();
        $this->assertSame(3, $stat->pages_viewed);
        $this->assertSame(90, $stat->seconds_read);
        $this->assertTrue($stat->date->isToday());
    }

    public function test_heartbeats_add_time_without_counting_pages(): void
    {
        $this->progress(4);
        $this->travel(60)->seconds();
        $this->progress(4);
        $this->travel(60)->seconds();
        $this->progress(4);

        $stat = ReadingDailyStat::sole();
        $this->assertSame(1, $stat->pages_viewed);
        $this->assertSame(120, $stat->seconds_read);
    }

    public function test_idle_gaps_are_not_counted_as_reading_time(): void
    {
        $this->progress(1);
        $this->travel(ReadingTracker::MAX_CREDITED_SECONDS + 1)->seconds();
        $this->progress(2);

        $this->assertSame(0, ReadingDailyStat::sole()->seconds_read);
    }

    public function test_requests_without_a_reading_session_are_not_tracked(): void
    {
        $this->actingAs($this->reader)->putJson(route('reader.progress', $this->book), ['position' => 2])->assertOk();

        $this->assertSame(0, ReadingDailyStat::count());
    }

    public function test_each_day_gets_its_own_row(): void
    {
        $this->progress(1);
        $this->travel(1)->day();
        $this->actingAs($this->reader)->get(route('reader.show', $this->book));
        $this->token = ReadingSession::latest('id')->value('token');
        $this->progress(2);

        $this->assertSame(2, ReadingDailyStat::count());
    }

    private function progress(int $position): TestResponse
    {
        return $this->actingAs($this->reader)
            ->withHeader('X-Reading-Session', $this->token)
            ->putJson(route('reader.progress', $this->book), ['position' => $position])
            ->assertOk();
    }
}
