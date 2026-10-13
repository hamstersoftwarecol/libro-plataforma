<x-app-layout :title="$book->title" bare>
    @php $hue = crc32($book->title) % 360; @endphp

    <section class="relative overflow-hidden border-b border-zinc-200/70 dark:border-zinc-800/70">
        <div class="absolute inset-0 opacity-60 dark:opacity-40" style="background: radial-gradient(80% 120% at 15% 0%, oklch(0.85 0.08 {{ $hue }} / .6), transparent 60%)"></div>
        <div class="relative mx-auto max-w-7xl px-4 pt-8 pb-16 sm:px-6 lg:px-8">
            <a href="{{ route('library') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-white"><x-icon name="arrow-left" class="size-4" />Biblioteca</a>

            <div class="mt-8 grid gap-10 md:grid-cols-[260px_1fr] lg:gap-16">
                <x-book-cover :book="$book" class="mx-auto w-48 shadow-2xl md:w-full" />

                <div class="animate-fade-up">
                    @if ($book->category)
                        <a href="{{ route('library', ['category' => $book->category->slug]) }}" class="eyebrow hover:underline">{{ $book->category->name }}</a>
                    @endif
                    <h1 class="mt-3 font-display text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-5xl">{{ $book->title }}</h1>
                    @if ($book->subtitle)
                        <p class="mt-2 text-xl text-zinc-600 dark:text-zinc-400">{{ $book->subtitle }}</p>
                    @endif
                    <p class="mt-4 text-lg">
                        @foreach ($book->authors as $author)
                            <a href="{{ route('library', ['author' => $author->slug]) }}" class="font-medium hover:text-brand-600">{{ $author->name }}</a>@if (! $loop->last)<span class="text-zinc-400">, </span>@endif
                        @endforeach
                    </p>

                    <dl class="mt-8 flex flex-wrap gap-3">
                        @foreach (array_filter([
                            ['Páginas', $book->page_count],
                            ['Año', $book->published_year],
                            ['Formato', $book->file_format ? strtoupper($book->file_format->value) : null],
                            ['Idioma', strtoupper($book->language)],
                        ], fn ($item) => $item[1]) as [$label, $value])
                            <div class="rounded-xl bg-white/70 px-4 py-2.5 ring-1 ring-zinc-200/70 backdrop-blur dark:bg-zinc-900/70 dark:ring-zinc-800">
                                <dt class="text-[11px] font-semibold tracking-wider text-zinc-500 uppercase">{{ $label }}</dt>
                                <dd class="mt-0.5 font-semibold tabular-nums">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="mt-8 max-w-md">
                        @can('read', $book)
                            <a href="{{ route('reader.show', $book) }}" class="btn btn-primary btn-lg">
                                <x-icon name="book" class="size-5" />{{ $progress ? 'Continuar leyendo' : 'Leer ahora' }}
                            </a>
                            @if ($progress)
                                <div class="mt-5">
                                    <div class="flex justify-between text-xs font-medium text-zinc-500">
                                        <span>{{ $book->file_format === \App\Enums\BookFormat::Epub ? 'Capítulo' : 'Página' }} {{ $progress->position }} de {{ $progress->total }}</span>
                                        <span>{{ number_format($progress->percentage, 0) }}%</span>
                                    </div>
                                    <div class="bar-track mt-2"><div class="bar-fill" style="width: {{ $progress->percentage }}%"></div></div>
                                </div>
                            @endif
                        @else
                            <div class="card flex items-start gap-4 p-5">
                                <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-300"><x-icon name="lock" /></div>
                                <div>
                                    @if (! $book->hasFile())
                                        <p class="font-semibold">Disponible muy pronto</p>
                                        <p class="mt-1 text-sm text-zinc-500">Este libro aún no está listo para lectura.</p>
                                    @elseif ($book->isForSale())
                                        <p class="font-semibold">Cómpralo una vez, léelo para siempre</p>
                                        <p class="mt-1 text-sm text-zinc-500">Pago único de <strong class="text-zinc-200">{{ $book->formattedPrice() }}</strong> con acceso de por vida.</p>
                                        <a href="{{ route('checkout.create', ['libro' => $book->slug]) }}" class="btn btn-primary btn-sm mt-4">Comprar y leer · {{ $book->formattedPrice() }} <x-icon name="arrow-right" class="size-3.5" /></a>
                                    @else
                                        <p class="font-semibold">Aún no está a la venta</p>
                                        <p class="mt-1 text-sm text-zinc-500">Vuelve pronto.</p>
                                    @endif
                                </div>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-[1fr_300px]">
            <div>
                @if ($book->description)
                    <h2 class="text-lg font-semibold">Sinopsis</h2>
                    <div class="mt-4 max-w-prose text-[17px] leading-relaxed whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $book->description }}</div>
                @endif
            </div>
            @if ($book->tags->isNotEmpty() || $book->isbn)
                <aside class="space-y-6">
                    @if ($book->tags->isNotEmpty())
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-500">Etiquetas</h3>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($book->tags as $tag)
                                    <a href="{{ route('library', ['tag' => $tag->slug]) }}" class="chip">#{{ $tag->name }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($book->isbn)
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-500">ISBN</h3>
                            <p class="mt-1 font-mono text-sm">{{ $book->isbn }}</p>
                        </div>
                    @endif
                </aside>
            @endif
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-16 border-t border-zinc-200/70 pt-12 dark:border-zinc-800/70">
                <h2 class="text-lg font-semibold">Más en {{ $book->category->name }}</h2>
                <div class="mt-6 grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-4">
                    @foreach ($related as $relatedBook)
                        <a href="{{ route('library.show', $relatedBook) }}" class="group">
                            <x-book-cover :book="$relatedBook" class="transition duration-300 group-hover:-translate-y-1.5 group-hover:shadow-2xl" />
                            <h3 class="mt-3 line-clamp-2 text-sm font-semibold group-hover:text-brand-600">{{ $relatedBook->title }}</h3>
                            <p class="text-xs text-zinc-500">{{ $relatedBook->authorNames() }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
