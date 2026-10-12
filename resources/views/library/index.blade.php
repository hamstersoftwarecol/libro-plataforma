<x-app-layout title="Biblioteca">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Biblioteca</p>
            <h1 class="page-title mt-2">Hola, {{ auth()->user()->displayName() }}</h1>
            <p class="mt-2 text-zinc-500">{{ $books->total() }} {{ $books->total() === 1 ? 'libro disponible' : 'libros disponibles' }} para ti.</p>
        </div>

        <form method="GET" action="{{ route('library') }}" class="flex w-full flex-wrap gap-2 lg:w-auto">
            <div class="relative min-w-0 flex-1 lg:w-80 lg:flex-none">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-zinc-400" />
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Título, autor o ISBN" class="input pl-10">
            </div>
            <select name="format" class="input w-auto" onchange="this.form.submit()">
                <option value="">Formato</option>
                <option value="pdf" @selected(($filters['format'] ?? null) === 'pdf')>PDF</option>
                <option value="epub" @selected(($filters['format'] ?? null) === 'epub')>EPUB</option>
            </select>
            @foreach (['author', 'tag', 'category'] as $hiddenFilter)
                @if (! empty($filters[$hiddenFilter]))
                    <input type="hidden" name="{{ $hiddenFilter }}" value="{{ $filters[$hiddenFilter] }}">
                @endif
            @endforeach
            <button class="btn btn-primary">Buscar</button>
        </form>
    </div>

    <div class="mt-8 flex gap-2 overflow-x-auto pb-2 [scrollbar-width:none]">
        <a href="{{ route('library', \Illuminate\Support\Arr::except(array_filter($filters), 'category')) }}" @class(['chip shrink-0', 'chip-active' => empty($filters['category'])])>Todo</a>
        @foreach ($categories as $category)
            <a href="{{ route('library', [...array_filter($filters), 'category' => $category->slug]) }}" @class(['chip shrink-0', 'chip-active' => ($filters['category'] ?? null) === $category->slug])>{{ $category->name }}</a>
        @endforeach
    </div>

    @if (! empty($filters['author']) || ! empty($filters['tag']) || ! empty($filters['q']))
        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-zinc-500">
            Filtrando por
            @foreach (['q' => 'búsqueda', 'author' => 'autor', 'tag' => 'etiqueta'] as $key => $label)
                @if (! empty($filters[$key]))
                    <span class="badge bg-brand-50 text-brand-700 ring-brand-200 dark:bg-brand-950 dark:text-brand-300 dark:ring-brand-900">{{ $label }}: {{ $filters[$key] }}</span>
                @endif
            @endforeach
            <a href="{{ route('library') }}" class="link text-sm">Limpiar</a>
        </div>
    @endif

    @if ($continueReading->isNotEmpty())
        <section class="mt-10">
            <h2 class="flex items-center gap-2 text-lg font-semibold"><x-icon name="clock" class="size-5 text-brand-600" />Continuar leyendo</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($continueReading->take(3) as $progress)
                    <a href="{{ route('reader.show', $progress->book) }}" class="card group flex items-center gap-4 p-4 transition hover:-translate-y-0.5 hover:shadow-lift">
                        <x-book-cover :book="$progress->book" compact class="w-14 shrink-0 rounded-lg" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold group-hover:text-brand-600">{{ $progress->book->title }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $progress->book->authorNames() }}</p>
                            <div class="bar-track mt-3"><div class="bar-fill" style="width: {{ $progress->percentage }}%"></div></div>
                            <p class="mt-1.5 text-xs font-medium text-zinc-500">{{ number_format($progress->percentage, 0) }}% leído</p>
                        </div>
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-600 text-white opacity-0 transition group-hover:opacity-100"><x-icon name="play" class="size-4" /></span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-12">
        @if ($continueReading->isNotEmpty())
            <h2 class="text-lg font-semibold">Todo el catálogo</h2>
        @endif

        @if ($books->isEmpty())
            <x-empty-state icon="search" title="No encontramos libros" description="Prueba con otra búsqueda o quita algún filtro." class="card mt-6">
                <a href="{{ route('library') }}" class="btn btn-secondary">Ver todo el catálogo</a>
            </x-empty-state>
        @else
            <div class="mt-6 grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                @foreach ($books as $book)
                    <a href="{{ route('library.show', $book) }}" class="group animate-fade-up" style="animation-delay: {{ $loop->index * 30 }}ms">
                        <x-book-cover :book="$book" class="transition duration-300 group-hover:-translate-y-1.5 group-hover:shadow-2xl" />
                        <h3 class="mt-3 line-clamp-2 text-sm leading-snug font-semibold group-hover:text-brand-600">{{ $book->title }}</h3>
                        <p class="mt-0.5 line-clamp-1 text-xs text-zinc-500">{{ $book->authorNames() }}</p>
                    </a>
                @endforeach
            </div>

            <div class="mt-12">{{ $books->links() }}</div>
        @endif
    </section>
</x-app-layout>
