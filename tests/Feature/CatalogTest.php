<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_lists_only_published_books(): void
    {
        Book::factory()->published()->create(['title' => 'Libro publicado']);
        Book::factory()->create(['title' => 'Libro en borrador']);
        Book::factory()->inactive()->create(['title' => 'Libro desactivado']);

        $this->actingAs(User::factory()->create())
            ->get(route('library'))
            ->assertOk()
            ->assertSee('Libro publicado')
            ->assertDontSee('Libro en borrador')
            ->assertDontSee('Libro desactivado');
    }

    public function test_catalog_requires_login(): void
    {
        $this->get(route('library'))->assertRedirect(route('login'));
    }

    public function test_search_matches_title_and_author(): void
    {
        $book = Book::factory()->published()->create(['title' => 'Rayuela']);
        $book->authors()->attach(Author::factory()->create(['name' => 'Julio Cortázar']));
        Book::factory()->published()->create(['title' => 'Otro libro']);

        $reader = User::factory()->create();

        $this->actingAs($reader)->get(route('library', ['q' => 'rayu']))->assertSee('Rayuela')->assertDontSee('Otro libro');
        $this->actingAs($reader)->get(route('library', ['q' => 'Cortázar']))->assertSee('Rayuela')->assertDontSee('Otro libro');
    }

    public function test_filters_by_category_tag_and_format(): void
    {
        $category = Category::factory()->create(['name' => 'Poesía']);
        $poetry = Book::factory()->published()->create(['title' => 'Veinte poemas', 'category_id' => $category->id]);
        $poetry->tags()->attach(Tag::factory()->create(['name' => 'amor']));
        Book::factory()->published()->create(['title' => 'Manual técnico', 'file_format' => 'epub']);

        $reader = User::factory()->create();

        $this->actingAs($reader)->get(route('library', ['category' => 'poesia']))->assertSee('Veinte poemas')->assertDontSee('Manual técnico');
        $this->actingAs($reader)->get(route('library', ['tag' => 'amor']))->assertSee('Veinte poemas')->assertDontSee('Manual técnico');
        $this->actingAs($reader)->get(route('library', ['format' => 'epub']))->assertSee('Manual técnico')->assertDontSee('Veinte poemas');
    }

    public function test_reader_sees_published_book_detail_but_not_drafts(): void
    {
        $reader = User::factory()->create();
        $published = Book::factory()->published()->create(['title' => 'Pedro Páramo']);
        $draft = Book::factory()->create();

        $this->actingAs($reader)->get(route('library.show', $published))->assertOk()->assertSee('Pedro Páramo');
        $this->actingAs($reader)->get(route('library.show', $draft))->assertForbidden();
    }

    public function test_staff_can_preview_draft_books(): void
    {
        $draft = Book::factory()->create();

        $this->actingAs(User::factory()->editor()->create())->get(route('library.show', $draft))->assertOk();
    }

    public function test_book_file_path_is_never_exposed(): void
    {
        $book = Book::factory()->published()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('library.show', $book))
            ->assertDontSee($book->file_path);

        $this->assertArrayNotHasKey('file_path', $book->toArray());
    }
}
