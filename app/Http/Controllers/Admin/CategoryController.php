<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaxonomyRequest;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(): View
    {
        return view('admin.taxonomies.index', [
            'items' => Category::withCount('books')->orderBy('name')->paginate(30),
            'type' => 'categories',
            'title' => 'Categorías',
        ]);
    }

    public function store(TaxonomyRequest $request): RedirectResponse
    {
        $request->validate(['name' => [Rule::unique('categories', 'name')]]);

        $category = Category::create($request->validated());
        $this->auditLogger->log('category.created', $category);

        return back()->with('status', 'Categoría creada.');
    }

    public function update(TaxonomyRequest $request, Category $category): RedirectResponse
    {
        $request->validate(['name' => [Rule::unique('categories', 'name')->ignore($category)]]);

        $category->update($request->validated());
        $this->auditLogger->log('category.updated', $category);

        return back()->with('status', 'Categoría actualizada.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->auditLogger->log('category.deleted', $category, ['name' => $category->name]);
        $category->delete();

        return back()->with('status', 'Categoría eliminada. Sus libros quedaron sin categoría.');
    }
}
