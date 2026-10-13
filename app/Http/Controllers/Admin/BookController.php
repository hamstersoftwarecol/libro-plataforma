<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookRequest;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Tag;
use App\Services\AuditLogger;
use App\Services\BookFileStorage;
use App\Services\Payments\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct(private BookFileStorage $files, private AuditLogger $auditLogger) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Book::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(BookStatus::class)],
        ]);

        $books = Book::query()
            ->with(['authors', 'category'])
            ->filter($filters)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.books.index', [
            'books' => $books,
            'filters' => $filters,
            'statuses' => BookStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Book::class);

        return view('admin.books.form', ['book' => new Book(['language' => 'es', 'currency' => 'COP']), 'categories' => Category::orderBy('name')->get()]);
    }

    /**
     * Create a book in draft status (HU-006, HU-007, HU-008).
     */
    public function store(BookRequest $request): RedirectResponse
    {
        $book = new Book($this->attributes($request));
        $book->status = BookStatus::Draft;
        $book->created_by = $request->user()->id;

        $this->persist($book, $request);

        $this->auditLogger->log('book.created', $book);

        return redirect()->route('admin.books.edit', $book)->with('status', 'Libro creado como borrador.');
    }

    public function edit(Book $book): View
    {
        Gate::authorize('update', $book);

        $book->load(['authors', 'tags']);

        return view('admin.books.form', ['book' => $book, 'categories' => Category::orderBy('name')->get()]);
    }

    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        $book->fill($this->attributes($request));

        $this->persist($book, $request);

        $this->auditLogger->log('book.updated', $book, ['changes' => array_keys($book->getChanges())]);

        return redirect()->route('admin.books.edit', $book)->with('status', 'Libro actualizado.');
    }

    /**
     * Make the book visible in the catalog. A file is required.
     */
    public function publish(Book $book): RedirectResponse
    {
        Gate::authorize('publish', $book);

        if (! $book->hasFile()) {
            return back()->withErrors(['file' => 'Sube el archivo PDF o EPUB antes de publicar.']);
        }

        $book->forceFill(['status' => BookStatus::Published, 'published_at' => $book->published_at ?? now()])->save();
        $this->auditLogger->log('book.published', $book);

        return back()->with('status', 'Libro publicado.');
    }

    /**
     * Hide the book from the catalog without deleting it.
     */
    public function deactivate(Book $book): RedirectResponse
    {
        Gate::authorize('publish', $book);

        $book->forceFill(['status' => BookStatus::Inactive])->save();
        $this->auditLogger->log('book.deactivated', $book);

        return back()->with('status', 'Libro desactivado.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        Gate::authorize('delete', $book);

        $this->files->deleteAll($book);
        $this->auditLogger->log('book.deleted', $book, ['title' => $book->title]);
        $book->delete();

        return redirect()->route('admin.books.index')->with('status', 'Libro eliminado.');
    }

    /**
     * Book fields with the price converted to minor units. Empty or zero price means "not for sale".
     *
     * @return array<string, mixed>
     */
    private function attributes(BookRequest $request): array
    {
        $attributes = $request->safe()->except(['authors', 'tags', 'cover', 'file']);
        $attributes['currency'] = strtoupper($attributes['currency'] ?? 'COP');
        $attributes['price'] = filled($attributes['price'] ?? null) && (float) $attributes['price'] > 0
            ? Money::toMinor($attributes['price'], $attributes['currency'])
            : null;

        return $attributes;
    }

    private function persist(Book $book, BookRequest $request): void
    {
        DB::transaction(function () use ($book, $request): void {
            $book->save();

            $book->authors()->sync(
                collect($request->names('authors'))->map(fn (string $name) => Author::firstOrCreate(['name' => $name])->id)
            );
            $book->tags()->sync(
                collect($request->names('tags'))->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id)
            );
        });

        if ($request->hasFile('cover')) {
            $this->files->storeCover($book, $request->file('cover'));
        }

        if ($request->hasFile('file')) {
            $this->files->storeBookFile($book, $request->file('file'));
        }
    }
}
