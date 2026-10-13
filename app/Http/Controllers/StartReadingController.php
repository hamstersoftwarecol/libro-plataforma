<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StartReadingController extends Controller
{
    /**
     * Where a customer lands: the book if they paid, the payment form if they did not.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('checkout.create');
        }

        $owned = $request->user()->books()->published()->latest('book_user.granted_at')->first();

        if ($request->user()->can('access-admin')) {
            return redirect()->route('admin.dashboard');
        }

        $book = Book::featured();

        if ($book && Gate::allows('read', $book)) {
            return redirect()->route('reader.show', $book);
        }

        if ($owned) {
            return redirect()->route('reader.show', $owned);
        }

        if (! $book) {
            return redirect()->route('library');
        }

        return redirect()->route('checkout.create', ['libro' => $book->slug])
            ->with('status', 'Completa el pago para leer «'.rtrim($book->title, '.').'». El acceso es de por vida.');
    }
}
