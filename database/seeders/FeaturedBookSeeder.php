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
use Illuminate\Support\Facades\Storage;

class FeaturedBookSeeder extends Seeder
{
    /**
     * The book the landing page is built around.
     */
    public function run(): void
    {
        $book = Book::firstOrNew(['slug' => 'el-fondo-no-era-el-final']);
        $book->fill([
            'title' => 'El fondo no era el final.',
            'subtitle' => 'Empezar a veces duele.',
            'description' => "¿Qué haces cuando la vida te obliga a comenzar de nuevo?\n\nUna historia de pérdidas, migración y renacimiento que puede parecerse a la tuya.",
            'language' => 'es',
            'category_id' => Category::firstOrCreate(['name' => 'Memorias'])->id,
        ]);
        $book->slug = 'el-fondo-no-era-el-final';
        $book->status = BookStatus::Published;
        $book->published_at ??= now();
        $book->save();

        $book->authors()->sync([Author::firstOrCreate(['name' => 'Manuel Augusto Peralta Rodríguez'])->id]);
        $book->tags()->sync(collect(['historia real', 'migración', 'renacimiento'])->map(fn (string $tag) => Tag::firstOrCreate(['name' => $tag])->id));

        $cover = public_path('images/flash.webp');
        if (is_file($cover)) {
            Storage::disk('public')->put('covers/el-fondo-no-era-el-final.webp', file_get_contents($cover));
            $book->forceFill(['cover_path' => 'covers/el-fondo-no-era-el-final.webp'])->save();
        }

        if (! $book->hasFile()) {
            $pages = ['El fondo no era el final.', 'Empezar a veces duele.', 'Manuel Augusto Peralta Rodriguez'];
            for ($page = 4; $page <= 20; $page++) {
                $pages[] = "Pagina {$page} - contenido de ejemplo";
            }

            $path = tempnam(sys_get_temp_dir(), 'seed');
            file_put_contents($path, SamplePdf::make($pages));

            try {
                app(BookFileStorage::class)->storeBookFile($book, new UploadedFile($path, 'el-fondo-no-era-el-final.pdf', 'application/pdf', test: true));
            } finally {
                @unlink($path);
            }
        }
    }
}
