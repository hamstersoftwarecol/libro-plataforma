<?php

namespace App\Services\Reader;

use App\Enums\BookFormat;
use App\Models\Book;
use App\Services\BookFileStorage;
use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Serves book content (rendered pages or chapters) from the encrypted original.
 */
class BookContent
{
    private const CACHE_DIR = '_cache';

    public function __construct(
        private BookVault $vault,
        private PdfRenderer $pdfRenderer,
        private EpubReader $epubReader,
        private Watermarker $watermarker,
    ) {}

    /**
     * Inspect a plain (not yet encrypted) file and return its navigable units.
     *
     * @return array{pages?: int, chapters?: list<string>}
     */
    public function analyze(string $plainPath, BookFormat $format): array
    {
        return match ($format) {
            BookFormat::Pdf => ['pages' => $this->pdfRenderer->pageCount($plainPath)],
            BookFormat::Epub => ['chapters' => $this->epubReader->chapters($plainPath)],
        };
    }

    /**
     * Watermarked WebP image of a PDF page.
     */
    public function pdfPage(Book $book, int $page, int $width, string $watermark): string
    {
        $png = $this->cached($book, "page-{$page}-{$width}.bin", fn (string $path): string => $this->pdfRenderer->renderPage($path, $page, $width));

        return $this->watermarker->apply($png, $watermark);
    }

    /**
     * Escaped HTML paragraphs with the text of a PDF page, so it can be translated in the browser.
     */
    public function pdfPageText(Book $book, int $page): string
    {
        $text = $this->cached($book, "text-{$page}.bin", fn (string $path): string => $this->pdfRenderer->pageText($path, $page));

        $paragraphs = collect(preg_split('/\n\s*\n/u', trim($text)))
            ->map(fn (string $paragraph) => trim(preg_replace('/\s*\n\s*/u', ' ', $paragraph)))
            ->filter();

        return $paragraphs->isEmpty()
            ? '<p class="text-center opacity-60">(Esta página no contiene texto: puede ser una imagen o ilustración.)</p>'
            : $paragraphs->map(fn (string $paragraph) => '<p>'.e($paragraph).'</p>')->join('');
    }

    /**
     * Sanitized HTML for an EPUB chapter.
     */
    public function epubChapter(Book $book, int $index): string
    {
        $chapterPath = $book->reading_units['chapters'][$index - 1] ?? throw new RuntimeException('Capítulo inexistente.');

        return $this->cached($book, "chapter-{$index}.bin", fn (string $path): string => $this->epubReader->chapterHtml($path, $chapterPath));
    }

    public function forget(Book $book): void
    {
        Storage::disk(BookFileStorage::DISK)->deleteDirectory(self::CACHE_DIR.'/'.$book->getKey());
    }

    /**
     * Render once from the decrypted original and keep the result encrypted on the private disk.
     *
     * @param  Closure(string): string  $render
     */
    private function cached(Book $book, string $name, Closure $render): string
    {
        $disk = Storage::disk(BookFileStorage::DISK);
        $cachePath = self::CACHE_DIR.'/'.$book->getKey().'/'.substr((string) $book->file_hash, 0, 16).'/'.$name;

        if ($disk->exists($cachePath)) {
            return Crypt::decryptString($disk->get($cachePath));
        }

        $plainPath = $this->plainPath($book);

        try {
            $contents = $render($plainPath);
        } finally {
            if ($book->file_encrypted) {
                @unlink($plainPath);
            }
        }

        $disk->put($cachePath, Crypt::encryptString($contents));

        return $contents;
    }

    private function plainPath(Book $book): string
    {
        $storedPath = Storage::disk(BookFileStorage::DISK)->path($book->file_path);

        return $book->file_encrypted ? $this->vault->decryptToTemporaryFile($storedPath) : $storedPath;
    }
}
