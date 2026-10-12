<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ReadingProgress;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BookFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookFixtures;
use Tests\TestCase;

class ReadingProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('books');

        $this->reader = User::factory()->create();
        Subscription::factory()->for($this->reader)->create();
        $this->book = Book::factory()->published()->create();
        app(BookFileStorage::class)->storeBookFile($this->book, BookFixtures::pdfUpload(4));
        $this->book->refresh();
    }

    public function test_progress_is_saved_and_resumed(): void
    {
        $this->actingAs($this->reader)->putJson(route('reader.progress', $this->book), ['position' => 3])
            ->assertOk()
            ->assertJson(['position' => 3, 'percentage' => 75]);

        $this->actingAs($this->reader)->get(route('reader.show', $this->book))->assertViewHas('position', 3);
        $this->actingAs($this->reader)->get(route('library.show', $this->book))->assertSee('Continuar leyendo')->assertSee('Página 3 de 4');
        $this->actingAs($this->reader)->get(route('library'))->assertSee('Continuar leyendo');
    }

    public function test_finishing_marks_the_book_as_read(): void
    {
        $this->actingAs($this->reader)->putJson(route('reader.progress', $this->book), ['position' => 4])->assertOk();

        $this->assertNotNull(ReadingProgress::sole()->finished_at);
    }

    public function test_position_must_be_within_the_book(): void
    {
        $this->actingAs($this->reader)->putJson(route('reader.progress', $this->book), ['position' => 5])->assertUnprocessable();
        $this->actingAs($this->reader)->putJson(route('reader.progress', $this->book), ['position' => 0])->assertUnprocessable();
    }

    public function test_progress_requires_reading_access(): void
    {
        $this->actingAs(User::factory()->create())->putJson(route('reader.progress', $this->book), ['position' => 2])->assertForbidden();
    }
}
