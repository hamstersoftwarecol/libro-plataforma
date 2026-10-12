<?php

namespace Database\Seeders;

use App\Enums\BookStatus;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Tag;
use App\Services\BookFileStorage;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class CatalogSeeder extends Seeder
{
    /**
     * Seed a demo catalog with small generated, encrypted PDF files.
     */
    public function run(): void
    {
        $catalog = [
            'Novela' => [
                ['Cien años de soledad', 'Gabriel García Márquez', 1967, ['clásico', 'realismo mágico']],
                ['Rayuela', 'Julio Cortázar', 1963, ['clásico', 'experimental']],
                ['Pedro Páramo', 'Juan Rulfo', 1955, ['clásico', 'realismo mágico']],
                ['La casa de los espíritus', 'Isabel Allende', 1982, ['saga familiar']],
            ],
            'Poesía' => [
                ['Veinte poemas de amor', 'Pablo Neruda', 1924, ['amor', 'clásico']],
                ['Antología poética', 'Gabriela Mistral', 1957, ['antología']],
            ],
            'Ensayo' => [
                ['El laberinto de la soledad', 'Octavio Paz', 1950, ['identidad', 'México']],
                ['Las venas abiertas de América Latina', 'Eduardo Galeano', 1971, ['historia', 'política']],
            ],
            'Ciencia ficción' => [
                ['La invención de Morel', 'Adolfo Bioy Casares', 1940, ['clásico', 'fantástico']],
            ],
        ];

        foreach ($catalog as $categoryName => $books) {
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($books as [$title, $authorName, $year, $tags]) {
                $book = Book::factory()->create([
                    'title' => $title,
                    'subtitle' => null,
                    'category_id' => $category->id,
                    'published_year' => $year,
                    'status' => BookStatus::Published,
                    'published_at' => now(),
                ]);

                $this->attachSampleFile($book, $title, $authorName);

                $book->authors()->attach(Author::firstOrCreate(['name' => $authorName]));
                $book->tags()->attach(collect($tags)->map(fn (string $tag) => Tag::firstOrCreate(['name' => $tag])->id));
            }
        }

        Book::factory()->create(['title' => 'Borrador de ejemplo', 'category_id' => Category::first()->id]);
    }

    private function attachSampleFile(Book $book, string $title, string $authorName): void
    {
        $pages = [$title, 'por '.$authorName];

        for ($page = 3; $page <= 12; $page++) {
            $pages[] = "Pagina {$page} - texto de ejemplo";
        }

        $path = tempnam(sys_get_temp_dir(), 'seed');
        file_put_contents($path, SamplePdf::make($pages));

        try {
            app(BookFileStorage::class)->storeBookFile($book, new UploadedFile($path, $book->slug.'.pdf', 'application/pdf', test: true));
        } finally {
            @unlink($path);
        }
    }
}
