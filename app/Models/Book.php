<?php

namespace App\Models;

use App\Enums\BookFormat;
use App\Enums\BookStatus;
use App\Models\Concerns\HasSlug;
use App\Services\Payments\Money;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['category_id', 'title', 'subtitle', 'description', 'isbn', 'language', 'published_year', 'page_count', 'price', 'currency'])]
#[Hidden(['file_path', 'file_hash', 'reading_units'])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, HasSlug;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookStatus::class,
            'file_format' => BookFormat::class,
            'published_at' => 'datetime',
            'published_year' => 'integer',
            'page_count' => 'integer',
            'file_size' => 'integer',
            'price' => 'integer',
            'file_encrypted' => 'boolean',
            'reading_units' => 'array',
        ];
    }

    protected function slugSource(): string
    {
        return 'title';
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<Author, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Readers who own this book for life.
     *
     * @return BelongsToMany<User, $this>
     */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['payment_id', 'granted_by', 'granted_at'])->withTimestamps();
    }

    /**
     * Published, with a file and a price: it can be bought.
     */
    public function isForSale(): bool
    {
        return $this->isPublished() && $this->hasFile() && $this->price !== null && $this->price > 0;
    }

    public function formattedPrice(): ?string
    {
        return $this->price ? Money::format($this->price, $this->currency) : null;
    }

    /**
     * @param  Builder<Book>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', BookStatus::Published);
    }

    /**
     * Search and filter the catalog (HU-010).
     *
     * @param  Builder<Book>  $query
     * @param  array{q?: ?string, category?: ?string, author?: ?string, tag?: ?string, format?: ?string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('subtitle', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('isbn', 'like', $like)
                        ->orWhereHas('authors', fn (Builder $query) => $query->where('name', 'like', $like));
                });
            })
            ->when($filters['category'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('category', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['author'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('authors', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['tag'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('tags', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['format'] ?? null, fn (Builder $query, string $format) => $query->where('file_format', $format));
    }

    /**
     * The book the platform is built around (landing page and post-login destination).
     */
    public static function featured(): ?self
    {
        return static::query()->published()->firstWhere('slug', config('app.featured_book'));
    }

    public function isPublished(): bool
    {
        return $this->status === BookStatus::Published;
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null;
    }

    /**
     * Number of navigable units: PDF pages or EPUB chapters.
     */
    public function unitCount(): int
    {
        return $this->file_format === BookFormat::Epub
            ? count($this->reading_units['chapters'] ?? [])
            : (int) ($this->reading_units['pages'] ?? $this->page_count ?? 0);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function authorNames(): string
    {
        return $this->authors->pluck('name')->join(', ', ' y ');
    }
}
