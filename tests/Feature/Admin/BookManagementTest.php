<?php

namespace Tests\Feature\Admin;

use App\Enums\BookFormat;
use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookFixtures;
use Tests\TestCase;

class BookManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('books');
        Storage::fake('public');
    }

    public function test_editor_creates_a_draft_book_with_file_cover_authors_and_tags(): void
    {
        $editor = User::factory()->editor()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($editor)->post(route('admin.books.store'), [
            'title' => 'Cien años de soledad',
            'language' => 'es',
            'category_id' => $category->id,
            'authors' => 'Gabriel García Márquez, gabriel garcía márquez',
            'tags' => 'realismo mágico, clásico',
            'cover' => UploadedFile::fake()->image('portada.jpg'),
            'file' => BookFixtures::pdfUpload(),
        ]);

        $book = Book::sole();
        $response->assertRedirect(route('admin.books.edit', $book));

        $this->assertSame(BookStatus::Draft, $book->status);
        $this->assertSame('cien-anos-de-soledad', $book->slug);
        $this->assertSame($editor->id, $book->created_by);
        $this->assertSame(BookFormat::Pdf, $book->file_format);
        $this->assertSame(64, strlen($book->file_hash));
        $this->assertTrue($book->file_encrypted);
        $this->assertSame(3, $book->page_count);
        $this->assertSame(3, $book->unitCount());
        $this->assertCount(1, $book->authors);
        $this->assertCount(2, $book->tags);
        Storage::disk('books')->assertExists($book->file_path);
        Storage::disk('public')->assertExists($book->cover_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'book.created', 'auditable_id' => $book->id]);
    }

    public function test_book_file_is_not_stored_on_a_public_disk(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.books.store'), [
            'title' => 'Privado',
            'language' => 'es',
            'file' => BookFixtures::epubUpload(),
        ]);

        $book = Book::sole();
        Storage::disk('public')->assertMissing($book->file_path);
        Storage::disk('books')->assertExists($book->file_path);
        $this->assertSame(BookFormat::Epub, $book->file_format);
        $this->assertSame(['OEBPS/text/uno.xhtml', 'OEBPS/text/dos.xhtml'], $book->reading_units['chapters']);
    }

    public function test_stored_file_is_encrypted_at_rest(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.books.store'), [
            'title' => 'Cifrado',
            'language' => 'es',
            'file' => BookFixtures::pdfUpload(),
        ]);

        $stored = Storage::disk('books')->get(Book::sole()->file_path);

        $this->assertStringNotContainsString('%PDF', $stored);
        $this->assertStringNotContainsString('Pagina 1', $stored);
    }

    public function test_corrupted_pdf_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.books.store'), [
            'title' => 'Dañado',
            'language' => 'es',
            'file' => UploadedFile::fake()->createWithContent('roto.pdf', "%PDF-1.4\nbasura"),
        ])->assertSessionHasErrors('file');

        Storage::disk('books')->assertDirectoryEmpty('/');
    }

    public function test_pdf_upload_succeeds_even_if_pdfinfo_path_is_misconfigured(): void
    {
        config(['reader.pdfinfo_path' => '/nonexistent/path/to/pdfinfo']);

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.books.store'), [
            'title' => 'PDF con fallback',
            'language' => 'es',
            'file' => BookFixtures::pdfUpload(3),
        ])->assertSessionHasNoErrors();

        $book = Book::where('title', 'PDF con fallback')->firstOrFail();
        $this->assertSame(3, $book->page_count);
    }

    public function test_invalid_file_types_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.books.store'), [
            'title' => 'Malicioso',
            'language' => 'es',
            'file' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_replacing_the_file_deletes_the_previous_one(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.books.store'), [
            'title' => 'Libro', 'language' => 'es',
            'file' => BookFixtures::pdfUpload(2, 'v1.pdf'),
        ]);
        $book = Book::sole();
        $oldPath = $book->file_path;

        $this->actingAs($admin)->put(route('admin.books.update', $book), [
            'title' => 'Libro', 'language' => 'es',
            'file' => BookFixtures::pdfUpload(4, 'v2.pdf'),
        ])->assertSessionHasNoErrors();

        Storage::disk('books')->assertMissing($oldPath);
        Storage::disk('books')->assertExists($book->fresh()->file_path);
        $this->assertSame(4, $book->fresh()->page_count);
    }

    public function test_book_cannot_be_published_without_a_file(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->editor()->create())
            ->post(route('admin.books.publish', $book))
            ->assertSessionHasErrors('file');

        $this->assertSame(BookStatus::Draft, $book->fresh()->status);
    }

    public function test_book_can_be_published_and_deactivated(): void
    {
        $editor = User::factory()->editor()->create();
        $book = Book::factory()->create(['file_path' => '1/libro.pdf', 'file_format' => 'pdf']);

        $this->actingAs($editor)->post(route('admin.books.publish', $book))->assertSessionHasNoErrors();
        $this->assertSame(BookStatus::Published, $book->fresh()->status);
        $this->assertNotNull($book->fresh()->published_at);

        $this->actingAs($editor)->post(route('admin.books.deactivate', $book));
        $this->assertSame(BookStatus::Inactive, $book->fresh()->status);
    }

    public function test_only_admin_can_delete_books(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->editor()->create())->delete(route('admin.books.destroy', $book))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.books.destroy', $book))
            ->assertRedirect(route('admin.books.index'));
        $this->assertModelMissing($book);
    }

    public function test_readers_cannot_manage_books(): void
    {
        $reader = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($reader)->get(route('admin.books.index'))->assertForbidden();
        $this->actingAs($reader)->post(route('admin.books.store'), ['title' => 'X', 'language' => 'es'])->assertForbidden();
        $this->actingAs($reader)->post(route('admin.books.publish', $book))->assertForbidden();
    }

    public function test_admin_book_list_filters_by_status(): void
    {
        Book::factory()->create(['title' => 'Borrador visible']);
        Book::factory()->published()->create(['title' => 'Publicado oculto']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.books.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('Borrador visible')
            ->assertDontSee('Publicado oculto');
    }

    public function test_slugs_are_unique(): void
    {
        $first = Book::factory()->create(['title' => 'El Principito']);
        $second = Book::factory()->create(['title' => 'El Principito']);

        $this->assertSame('el-principito', $first->slug);
        $this->assertSame('el-principito-2', $second->slug);
    }
}
