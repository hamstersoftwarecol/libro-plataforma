<?php

namespace App\Services;

use App\Enums\BookFormat;
use App\Models\Book;
use App\Services\Reader\BookContent;
use App\Services\Reader\BookVault;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BookFileStorage
{
    public const DISK = 'books';

    public function __construct(private BookVault $vault, private BookContent $content) {}

    /**
     * Validate, encrypt and store the book file on the private disk (HU-007, HU-012).
     *
     * @throws ValidationException
     */
    public function storeBookFile(Book $book, UploadedFile $file): void
    {
        $format = BookFormat::from(strtolower($file->getClientOriginalExtension()));

        try {
            $readingUnits = $this->content->analyze($file->getRealPath(), $format);
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'file' => 'El archivo está dañado o no es un '.strtoupper($format->value).' válido.',
            ]);
        }

        $disk = Storage::disk(self::DISK);
        $previousPath = $book->file_path;
        $path = $book->getKey().'/'.Str::uuid().'.'.$format->value.'.enc';

        $disk->makeDirectory((string) $book->getKey());
        $this->vault->encryptFile($file->getRealPath(), $disk->path($path));

        $this->content->forget($book);

        $book->forceFill([
            'file_path' => $path,
            'file_format' => $format,
            'file_size' => $file->getSize(),
            'file_hash' => hash_file('sha256', $file->getRealPath()),
            'file_encrypted' => true,
            'reading_units' => $readingUnits,
            'page_count' => $readingUnits['pages'] ?? $book->page_count,
        ])->save();

        if ($previousPath && $previousPath !== $path) {
            $disk->delete($previousPath);
        }
    }

    /**
     * Store the cover image on the public disk.
     */
    public function storeCover(Book $book, UploadedFile $cover): void
    {
        $previousPath = $book->cover_path;

        $book->forceFill(['cover_path' => $cover->store('covers', 'public')])->save();

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }
    }

    public function deleteAll(Book $book): void
    {
        if ($book->file_path) {
            Storage::disk(self::DISK)->deleteDirectory((string) $book->getKey());
            $this->content->forget($book);
        }

        if ($book->cover_path) {
            Storage::disk('public')->delete($book->cover_path);
        }
    }
}
