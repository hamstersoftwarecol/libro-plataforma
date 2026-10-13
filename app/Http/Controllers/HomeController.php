<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Public landing page with a preview of the catalog and plans.
     */
    public function __invoke(): View
    {
        $featured = Book::published()->with(['authors', 'category'])->firstWhere('slug', config('app.featured_book'));

        return view('welcome', [
            'featured' => $featured,
            'books' => Book::published()->with('authors')->when($featured, fn ($query) => $query->whereKeyNot($featured->id))->latest('published_at')->limit(6)->get(),
            'bookCount' => Book::published()->count(),
            'categories' => Category::whereHas('books', fn ($query) => $query->published())->withCount(['books' => fn ($query) => $query->published()])->orderByDesc('books_count')->limit(6)->get(),
        ]);
    }
}
