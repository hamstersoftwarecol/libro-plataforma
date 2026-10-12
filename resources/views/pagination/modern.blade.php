@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación" class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-zinc-500">
            Mostrando <span class="font-semibold text-zinc-900 dark:text-white">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span> de <span class="font-semibold text-zinc-900 dark:text-white">{{ $paginator->total() }}</span>
        </p>

        <div class="flex items-center gap-1 rounded-xl bg-white p-1 shadow-soft ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-800">
            @if ($paginator->onFirstPage())
                <span class="grid size-9 place-items-center rounded-lg text-zinc-300 dark:text-zinc-700"><x-icon name="chevron-left" class="size-4" /></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="grid size-9 place-items-center rounded-lg text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800" aria-label="Anterior"><x-icon name="chevron-left" class="size-4" /></a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="grid size-9 place-items-center text-sm text-zinc-400">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="grid size-9 place-items-center rounded-lg bg-brand-600 text-sm font-semibold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="hidden size-9 place-items-center rounded-lg text-sm font-medium text-zinc-600 hover:bg-zinc-100 sm:grid dark:text-zinc-400 dark:hover:bg-zinc-800">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="grid size-9 place-items-center rounded-lg text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800" aria-label="Siguiente"><x-icon name="chevron-right" class="size-4" /></a>
            @else
                <span class="grid size-9 place-items-center rounded-lg text-zinc-300 dark:text-zinc-700"><x-icon name="chevron-right" class="size-4" /></span>
            @endif
        </div>
    </nav>
@endif
