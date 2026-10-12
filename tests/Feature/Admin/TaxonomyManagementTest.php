<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_manages_categories(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post(route('admin.categories.store'), ['name' => 'Ciencia ficción'])->assertSessionHasNoErrors();
        $category = Category::sole();
        $this->assertSame('ciencia-ficcion', $category->slug);

        $this->actingAs($editor)->post(route('admin.categories.store'), ['name' => 'Ciencia ficción'])->assertSessionHasErrors('name');

        $this->actingAs($editor)->put(route('admin.categories.update', $category), ['name' => 'Fantasía'])->assertSessionHasNoErrors();
        $this->assertSame('Fantasía', $category->fresh()->name);

        $this->actingAs($editor)->get(route('admin.categories.index'))->assertOk()->assertSee('Fantasía');
    }

    public function test_deleting_a_category_keeps_its_books(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.categories.destroy', $book->category));

        $this->assertNull($book->fresh()->category_id);
    }

    public function test_editor_manages_authors(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post(route('admin.authors.store'), ['name' => 'Isabel Allende', 'description' => 'Escritora chilena.']);
        $author = Author::sole();
        $this->assertSame('Escritora chilena.', $author->bio);

        $this->actingAs($editor)->delete(route('admin.authors.destroy', $author));
        $this->assertModelMissing($author);
    }

    public function test_readers_cannot_manage_taxonomies(): void
    {
        $this->actingAs(User::factory()->create())->post(route('admin.categories.store'), ['name' => 'X'])->assertForbidden();
    }
}
