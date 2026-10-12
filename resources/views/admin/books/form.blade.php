<x-admin-layout :title="$book->exists ? 'Editar libro' : 'Nuevo libro'" :heading="$book->exists ? $book->title : 'Nuevo libro'" :back="route('admin.books.index')">
    @if ($book->exists)
        <x-slot:actions>
            <span class="{{ $book->status->badgeClasses() }}">{{ $book->status->label() }}</span>
            @if ($book->isPublished())
                <a href="{{ route('library.show', $book) }}" class="btn btn-secondary"><x-icon name="eye" class="size-4" />Ver en catálogo</a>
                <form method="POST" action="{{ route('admin.books.deactivate', $book) }}">@csrf<button class="btn btn-danger"><x-icon name="ban" class="size-4" />Desactivar</button></form>
            @else
                <form method="POST" action="{{ route('admin.books.publish', $book) }}">@csrf<button class="btn btn-success"><x-icon name="globe" class="size-4" />Publicar</button></form>
            @endif
        </x-slot:actions>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ $book->exists ? route('admin.books.update', $book) : route('admin.books.store') }}" class="grid gap-6 lg:grid-cols-[1fr_320px]">
        @csrf
        @if ($book->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Información</h2></div>
            <div class="card-body space-y-5">
                <div>
                    <label for="title" class="label">Título *</label>
                    <input id="title" name="title" value="{{ old('title', $book->title) }}" required class="input">
                </div>
                <div>
                    <label for="subtitle" class="label">Subtítulo</label>
                    <input id="subtitle" name="subtitle" value="{{ old('subtitle', $book->subtitle) }}" class="input">
                </div>
                <div>
                    <label for="authors" class="label">Autores</label>
                    <input id="authors" name="authors" value="{{ old('authors', $book->exists ? $book->authors->pluck('name')->join(', ') : '') }}" class="input" placeholder="Separados por coma">
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="category_id" class="label">Categoría</label>
                        <select id="category_id" name="category_id" class="input">
                            <option value="">Sin categoría</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id', $book->category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="tags" class="label">Etiquetas</label>
                        <input id="tags" name="tags" value="{{ old('tags', $book->exists ? $book->tags->pluck('name')->join(', ') : '') }}" class="input" placeholder="Separadas por coma">
                    </div>
                </div>
                <div>
                    <label for="description" class="label">Sinopsis</label>
                    <textarea id="description" name="description" rows="7" class="input">{{ old('description', $book->description) }}</textarea>
                </div>
                <div class="grid gap-5 sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label for="isbn" class="label">ISBN</label>
                        <input id="isbn" name="isbn" value="{{ old('isbn', $book->isbn) }}" class="input font-mono">
                    </div>
                    <div>
                        <label for="published_year" class="label">Año</label>
                        <input id="published_year" name="published_year" type="number" value="{{ old('published_year', $book->published_year) }}" class="input">
                    </div>
                    <div>
                        <label for="language" class="label">Idioma</label>
                        <input id="language" name="language" maxlength="2" value="{{ old('language', $book->language) }}" required class="input uppercase">
                    </div>
                </div>
                <div class="sm:w-1/4">
                    <label for="page_count" class="label">Páginas</label>
                    <input id="page_count" name="page_count" type="number" value="{{ old('page_count', $book->page_count) }}" class="input">
                    <p class="hint">Se calcula solo al subir un PDF.</p>
                </div>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="card card-body">
                <h2 class="card-title">Portada</h2>
                @if ($book->exists)
                    <x-book-cover :book="$book" class="mx-auto mt-4 w-36" />
                @endif
                <input name="cover" type="file" accept="image/jpeg,image/png,image/webp" class="input-file mt-4">
                <p class="hint">JPG, PNG o WebP · máx. 4 MB</p>
            </div>

            <div class="card card-body">
                <h2 class="card-title">Archivo del libro</h2>
                @if ($book->hasFile())
                    <div class="mt-4 flex items-center gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                        <div class="grid size-10 place-items-center rounded-lg bg-white font-mono text-xs font-bold text-brand-600 uppercase ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-700">{{ $book->file_format->value }}</div>
                        <div class="text-sm">
                            <p class="font-medium">{{ number_format($book->file_size / 1048576, 1) }} MB · {{ $book->unitCount() }} {{ $book->file_format === \App\Enums\BookFormat::Epub ? 'capítulos' : 'páginas' }}</p>
                            <p class="flex items-center gap-1 text-xs text-emerald-600"><x-icon name="lock" class="size-3" />Cifrado en reposo</p>
                        </div>
                    </div>
                @endif
                <input name="file" type="file" accept=".pdf,.epub" class="input-file mt-4">
                <p class="hint">PDF o EPUB · máx. 100 MB. Se cifra y nunca se publica.</p>
            </div>

            <button class="btn btn-primary w-full py-3">{{ $book->exists ? 'Guardar cambios' : 'Crear borrador' }}</button>

            @if ($book->exists)
                @can('delete', $book)
                    <button form="delete-book" class="btn btn-ghost w-full text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"><x-icon name="trash" class="size-4" />Eliminar libro</button>
                @endcan
            @endif
        </aside>
    </form>

    @if ($book->exists)
        <form id="delete-book" method="POST" action="{{ route('admin.books.destroy', $book) }}" onsubmit="return confirm('¿Eliminar este libro y su archivo de forma permanente?')">@csrf @method('DELETE')</form>
    @endif
</x-admin-layout>
