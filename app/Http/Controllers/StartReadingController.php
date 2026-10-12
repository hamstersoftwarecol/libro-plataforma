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
        if ($request->user()->can('access-admin')) {
            return redirect()->route('admin.dashboard');
        }

        $book = Book::featured();

        if (! $book) {
            return redirect()->route('library');
        }

        if (Gate::allows('read', $book)) {
            return redirect()->route('reader.show', $book);
        }

        return redirect()->route('checkout.create')
            ->with('status', 'Completa el pago para leer «'.rtrim($book->title, '.').'». El acceso se activa en cuanto se confirme.');
    }
}
