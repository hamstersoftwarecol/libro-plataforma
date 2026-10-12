<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaxonomyRequest;
use App\Models\Author;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AuthorController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(): View
    {
        return view('admin.taxonomies.index', [
            'items' => Author::withCount('books')->orderBy('name')->paginate(30),
            'type' => 'authors',
            'title' => 'Autores',
        ]);
    }

    public function store(TaxonomyRequest $request): RedirectResponse
    {
        $author = Author::create(['name' => $request->validated('name'), 'bio' => $request->validated('description')]);
        $this->auditLogger->log('author.created', $author);

        return back()->with('status', 'Autor creado.');
    }

    public function update(TaxonomyRequest $request, Author $author): RedirectResponse
    {
        $author->update(['name' => $request->validated('name'), 'bio' => $request->validated('description')]);
        $this->auditLogger->log('author.updated', $author);

        return back()->with('status', 'Autor actualizado.');
    }

    public function destroy(Author $author): RedirectResponse
    {
        $this->auditLogger->log('author.deleted', $author, ['name' => $author->name]);
        $author->delete();

        return back()->with('status', 'Autor eliminado.');
    }
}
