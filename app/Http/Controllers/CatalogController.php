<?php

namespace App\Http\Controllers;

use App\Enums\BookFormat;
use App\Models\Book;
use App\Models\Category;
use App\Models\ReadingProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /**
     * Published catalog with search and filters (HU-009, HU-010).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
            'format' => ['nullable', Rule::enum(BookFormat::class)],
        ]);

        $books = Book::query()
            ->published()
            ->with('authors')
            ->filter($filters)
            ->latest('published_at')
            ->paginate(24)
            ->withQueryString();

        return view('library.index', [
            'books' => $books,
            'filters' => $filters,
            'continueReading' => array_filter($filters) ? collect() : $request->user()->readingProgress()
                ->whereNull('finished_at')
                ->whereHas('book', fn ($query) => $query->published())
                ->with('book.authors')
                ->latest('updated_at')
                ->limit(6)
                ->get(),
            'categories' => Category::whereHas('books', fn ($query) => $query->published())->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, Book $book): View
    {
        Gate::authorize('view', $book);

        $book->load(['authors', 'tags', 'category']);

        return view('library.show', [
            'book' => $book,
            'progress' => ReadingProgress::firstWhere(['user_id' => $request->user()->id, 'book_id' => $book->id]),
            'related' => Book::published()
                ->whereKeyNot($book->id)
                ->where('category_id', $book->category_id)
                ->with('authors')
                ->limit(4)
                ->get(),
        ]);
    }
}
