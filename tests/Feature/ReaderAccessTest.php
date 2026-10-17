<?php

namespace Tests\Feature;

use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\ReadingSession;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BookFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookFixtures;
use Tests\TestCase;

class ReaderAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('books');
    }

    public function test_subscriber_reads_a_watermarked_pdf_page(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook();

        $token = $this->openReader($reader, $book);

        $response = $this->actingAs($reader)
            ->withHeader('X-Reading-Session', $token)
            ->get(route('reader.page', [$book, 1, 'w' => 800]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Content-Disposition', 'inline');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $image = imagecreatefromstring($response->getContent());
        $this->assertSame(800, imagesx($image));
    }

    public function test_reader_view_never_exposes_the_original_file(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook();

        $this->actingAs($reader)->get(route('reader.show', $book))
            ->assertOk()
            ->assertDontSee($book->file_path)
            ->assertDontSee('.pdf.enc');
    }

    public function test_rendered_pages_are_cached_encrypted(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook();
        $token = $this->openReader($reader, $book);

        $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->get(route('reader.page', [$book, 2, 'w' => 800]))->assertOk();

        $cached = collect(Storage::disk('books')->allFiles('_cache'));
        $this->assertCount(1, $cached);
        $stored = Storage::disk('books')->get($cached->first());
        $this->assertStringNotContainsString("\x89PNG", $stored);
        $this->assertStringStartsWith("\x89PNG", Crypt::decryptString($stored));
    }

    public function test_reader_without_subscription_is_denied(): void
    {
        $reader = User::factory()->create();
        Subscription::factory()->expired()->for($reader)->create();
        $book = $this->publishedBook();

        $this->actingAs($reader)->get(route('reader.show', $book))->assertForbidden();
        $this->actingAs($reader)->get(route('library.show', $book))->assertSee('Aún no está a la venta');

        $book->forceFill(['price' => 50000, 'currency' => 'COP'])->save();
        $this->actingAs($reader)->get(route('reader.show', $book))->assertRedirect(route('checkout.create', ['libro' => $book->slug]));
        $this->actingAs($reader)->get(route('library.show', $book))->assertSee('Comprar y leer');

        $reader->books()->attach($book->id, ['granted_at' => now()]);
        $this->actingAs($reader)->get(route('reader.show', $book))->assertOk();
    }

    public function test_access_is_rechecked_on_every_page(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook();
        $token = $this->openReader($reader, $book);

        $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->get(route('reader.page', [$book, 1]))->assertOk();

        $this->travel(2)->months();

        $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->get(route('reader.page', [$book, 2]))->assertForbidden();
    }

    public function test_deactivated_book_stops_being_served(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook();
        $token = $this->openReader($reader, $book);

        $book->forceFill(['status' => BookStatus::Inactive])->save();

        $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->get(route('reader.page', [$book, 1]))->assertForbidden();
    }

    public function test_page_requests_require_a_reading_session(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook();

        $this->actingAs($reader)->get(route('reader.page', [$book, 1]))->assertStatus(409);
        $this->actingAs($reader)->withHeader('X-Reading-Session', 'inventado')->get(route('reader.page', [$book, 1]))->assertStatus(409);
    }

    public function test_opening_a_second_session_revokes_the_first(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook();

        $firstToken = $this->openReader($reader, $book);
        $secondToken = $this->openReader($reader, $book);

        $this->actingAs($reader)->withHeader('X-Reading-Session', $firstToken)->get(route('reader.page', [$book, 1]))->assertStatus(409);
        $this->actingAs($reader)->withHeader('X-Reading-Session', $secondToken)->get(route('reader.page', [$book, 1]))->assertOk();
    }

    public function test_concurrent_session_limit_is_configurable(): void
    {
        config(['reader.max_concurrent_sessions' => 2]);
        $reader = $this->subscriber();
        $book = $this->publishedBook();

        $first = $this->openReader($reader, $book);
        $this->openReader($reader, $book);
        $this->assertNull(ReadingSession::firstWhere('token', $first)->revoked_at);

        $this->openReader($reader, $book);
        $this->assertNotNull(ReadingSession::firstWhere('token', $first)->revoked_at);
    }

    public function test_idle_sessions_expire(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook();
        $token = $this->openReader($reader, $book);

        $this->travel(config('reader.session_idle_minutes') + 1)->minutes();

        $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->get(route('reader.page', [$book, 1]))->assertStatus(409);
    }

    public function test_out_of_range_pages_return_not_found(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook();
        $token = $this->openReader($reader, $book);

        $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->get(route('reader.page', [$book, 99]))->assertNotFound();
        $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->get(route('reader.page', [$book, 1, 'w' => 5000]))->assertSessionHasErrors('w');
    }

    public function test_pdf_page_text_is_served_for_translation_with_the_same_protection(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook();
        $token = $this->openReader($reader, $book);

        $response = $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->getJson(route('reader.text', [$book, 2]));

        $response->assertOk()
            ->assertJsonPath('html', '<p>Pagina 2</p>')
            ->assertJsonPath('watermark', fn (string $watermark) => str_contains($watermark, $reader->email));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->flushHeaders()->actingAs($reader)->getJson(route('reader.text', [$book, 2]))->assertStatus(409);
        $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->getJson(route('reader.text', [$book, 99]))->assertNotFound();

        $this->travel(2)->months();
        $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->getJson(route('reader.text', [$book, 1]))->assertForbidden();
    }

    public function test_pdf_page_text_returns_200_even_if_pdftotext_is_misconfigured(): void
    {
        config(['reader.pdftotext_path' => '/nonexistent/path/to/pdftotext']);

        $reader = $this->subscriber();
        $book = $this->publishedBook();
        $token = $this->openReader($reader, $book);

        $response = $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->getJson(route('reader.text', [$book, 2]));

        $response->assertOk()
            ->assertJsonPath('html', '<p>Pagina 2</p>');
    }

    public function test_page_text_is_not_available_for_epub_books(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook(epub: true);
        $token = $this->openReader($reader, $book);

        $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->getJson(route('reader.text', [$book, 1]))->assertNotFound();
    }

    public function test_epub_chapters_are_sanitized(): void
    {
        $reader = $this->subscriber();
        $book = $this->publishedBook(epub: true);
        $token = $this->openReader($reader, $book);

        $response = $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->getJson(route('reader.chapter', [$book, 1]));

        $response->assertOk()->assertJsonPath('watermark', fn (string $watermark) => str_contains($watermark, $reader->email));
        $html = $response->json('html');

        $this->assertStringContainsString('Capítulo uno', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('malo.example', $html);
        $this->assertStringNotContainsString('style=', $html);

        $this->actingAs($reader)->withHeader('X-Reading-Session', $token)->get(route('reader.page', [$book, 1]))->assertNotFound();
    }

    public function test_staff_can_read_drafts_without_subscription(): void
    {
        $book = $this->publishedBook();
        $book->forceFill(['status' => BookStatus::Draft])->save();

        $this->actingAs(User::factory()->editor()->create())->get(route('reader.show', $book))->assertOk();
    }

    public function test_readers_cannot_read_drafts(): void
    {
        $book = $this->publishedBook();
        $book->forceFill(['status' => BookStatus::Draft])->save();

        $this->actingAs($this->subscriber())->get(route('reader.show', $book))->assertForbidden();
    }

    private function subscriber(): User
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create();

        return $user;
    }

    private function publishedBook(bool $epub = false): Book
    {
        $book = Book::factory()->create(['status' => BookStatus::Published, 'published_at' => now()]);
        app(BookFileStorage::class)->storeBookFile($book, $epub ? BookFixtures::epubUpload() : BookFixtures::pdfUpload());

        return $book->fresh();
    }

    private function openReader(User $user, Book $book): string
    {
        $this->actingAs($user)->get(route('reader.show', $book))->assertOk();

        return ReadingSession::where('user_id', $user->id)->latest('id')->value('token');
    }
}
