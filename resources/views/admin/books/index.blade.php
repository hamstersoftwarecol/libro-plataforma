<x-admin-layout title="Libros" heading="Libros" description="Gestiona el catálogo, los archivos y su publicación.">
    <x-slot:actions>
        <a href="{{ route('admin.books.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Nuevo libro</a>
    </x-slot:actions>

    <form method="GET" class="mb-6 flex flex-wrap gap-2">
        <div class="relative min-w-60 flex-1 sm:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Título, autor o ISBN" class="input pl-10">
        </div>
        <select name="status" class="input w-auto" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary"><x-icon name="filter" class="size-4" />Filtrar</button>
    </form>

    <div class="table-wrap">
        @if ($books->isEmpty())
            <x-empty-state icon="book" title="No hay libros" description="Crea el primero y súbele un PDF o EPUB.">
                <a href="{{ route('admin.books.create') }}" class="btn btn-primary">Nuevo libro</a>
            </x-empty-state>
        @else
            <table class="table">
                <thead><tr><th>Libro</th><th>Categoría</th><th class="text-right">Precio</th><th>Archivo</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @foreach ($books as $book)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-book-cover :book="$book" compact class="w-10 shrink-0 rounded-md shadow-sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.books.edit', $book) }}" class="block truncate font-semibold hover:text-brand-600">{{ $book->title }}</a>
                                        <p class="truncate text-xs text-zinc-500">{{ $book->authorNames() ?: 'Sin autor' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-zinc-500">{{ $book->category?->name ?? '—' }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ $book->formattedPrice() ?? '—' }}</td>
                            <td>
                                @if ($book->file_format)
                                    <span class="badge bg-zinc-50 font-mono text-zinc-600 uppercase ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700">{{ $book->file_format->value }}</span>
                                @else
                                    <span class="text-xs text-zinc-400">Sin archivo</span>
                                @endif
                            </td>
                            <td><span class="{{ $book->status->badgeClasses() }}">{{ $book->status->label() }}</span></td>
                            <td class="text-right"><a href="{{ route('admin.books.edit', $book) }}" class="btn btn-ghost btn-sm">Editar</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-6">{{ $books->links() }}</div>
</x-admin-layout>
