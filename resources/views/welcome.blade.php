@php
    $title = $featured?->title ?? 'El fondo no era el final.';
    $author = $featured?->authorNames() ?: 'Manuel Augusto Peralta Rodríguez';
    $readUrl = route('book.start');
    $dust = collect(range(1, 26))->map(fn ($index) => [
        'left' => ($index * 37) % 100,
        'size' => 1 + ($index % 3),
        'duration' => 12 + ($index * 7) % 14,
        'delay' => -(($index * 13) % 20),
        'drift' => (($index % 2) ? 1 : -1) * (20 + ($index * 11) % 60),
    ]);
@endphp

<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $author }}</title>
    <meta name="description" content="¿Qué haces cuando la vida te obliga a comenzar de nuevo? Una historia de pérdidas, migración y renacimiento que puede parecerse a la tuya.">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="Una historia real de migración, caídas y renacimientos.">
    <meta property="og:image" content="{{ asset('images/flash.webp') }}">
    <meta name="theme-color" content="#0f0d0b">
    <link rel="preload" as="image" href="{{ asset('images/flash.webp') }}">
    <x-fonts />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cine cine-grain overflow-x-hidden antialiased">
    <div class="cine-letterbox top-0" aria-hidden="true"></div>
    <div class="cine-letterbox bottom-0" aria-hidden="true"></div>

    {{-- Navigation --}}
    <header id="nav" class="fixed inset-x-0 top-0 z-50 transition duration-500">
        <nav class="mx-auto flex h-20 max-w-7xl items-center gap-8 px-6 lg:px-10">
            <a href="{{ route('home') }}" class="notranslate font-display text-lg font-semibold tracking-tight text-paper" translate="no">{{ config('app.name') }}</a>
            <div class="hidden items-center gap-8 text-sm text-paper/60 md:flex">
                <a href="#historia" class="transition hover:text-paper">La historia</a>
                <a href="#actos" class="transition hover:text-paper">El viaje</a>
                <a href="#autor" class="transition hover:text-paper">El autor</a>
                <a href="#leer" class="transition hover:text-paper">Leer</a>
            </div>
            <div class="ml-auto flex items-center gap-3">
                <x-language-switcher variant="cinematic" class="hidden sm:flex" />
                @auth
                    <a href="{{ route('library') }}" class="text-sm font-medium text-paper/70 transition hover:text-paper">Mi biblioteca</a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-paper/70 transition hover:text-paper">Ingresar</a>
                @endauth
                <a href="{{ $readUrl }}" class="rounded-full bg-paper px-5 py-2.5 text-sm font-semibold text-night transition hover:bg-white">Leer ahora</a>
            </div>
        </nav>
    </header>

    {{-- Hero --}}
    <section class="relative flex min-h-svh items-center overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_70%_75%,oklch(0.32_0.07_50),oklch(0.13_0.008_60)_60%)]"></div>
        <div class="cine-sun absolute top-1/2 right-[-12rem] size-[44rem] max-w-none -translate-y-1/3 rounded-full blur-2xl lg:right-[-6rem]"></div>
        <div class="cine-horizon absolute inset-x-0 bottom-[14%] h-px"></div>
        <div class="absolute inset-x-0 bottom-0 h-[14%] bg-gradient-to-b from-[oklch(0.18_0.02_55)] to-night"></div>
        <div class="cine-dust pointer-events-none absolute inset-0" aria-hidden="true">
            @foreach ($dust as $particle)
                <span style="left: {{ $particle['left'] }}%; --size: {{ $particle['size'] }}px; --duration: {{ $particle['duration'] }}s; --delay: {{ $particle['delay'] }}s; --drift: {{ $particle['drift'] }}px"></span>
            @endforeach
        </div>
        <div class="cine-vignette pointer-events-none absolute inset-0"></div>

        <div class="relative mx-auto grid w-full max-w-7xl items-center gap-12 px-6 pt-28 pb-24 lg:grid-cols-[1.15fr_1fr] lg:px-10">
            <div>
                <p class="cine-line mb-8 text-xs font-semibold tracking-[0.35em] text-ember-400 uppercase" style="--delay: 1900ms">Una historia real de migración, caídas y renacimientos</p>

                <h1 class="notranslate font-hand text-[clamp(3.25rem,7vw,6.5rem)] leading-[0.88] font-medium" translate="no">
                    <span class="cine-line" style="--delay: 2200ms" data-en="The bottom">El fondo</span>
                    <span class="cine-line" style="--delay: 2700ms" data-en="wasn't">no era</span>
                    <span class="cine-line relative inline-block font-bold text-blood-500" style="--delay: 3300ms">
                        <span data-en="the end.">el final.</span>
                        <svg class="absolute -bottom-3 left-0 w-full" viewBox="0 0 600 24" fill="none" preserveAspectRatio="none" aria-hidden="true">
                            <path class="cine-underline" style="--delay: 4100ms" d="M4 16 C 120 6, 300 22, 596 10" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                        </svg>
                    </span>
                </h1>

                <p class="cine-line notranslate mt-8 font-hand text-[clamp(1.75rem,3vw,2.6rem)] leading-none text-paper/90" style="--delay: 4300ms" translate="no">
                    <span data-en="Starting over sometimes hurts.">Empezar a veces duele.</span>
                </p>

                <div class="cine-line mt-12 flex flex-wrap items-center gap-4" style="--delay: 4700ms">
                    <a href="{{ $readUrl }}" class="group inline-flex items-center gap-3 rounded-full bg-gradient-to-r from-ember-500 to-blood-500 px-8 py-4 text-base font-semibold text-white shadow-[0_20px_60px_-15px_oklch(0.6_0.18_40/0.8)] transition hover:shadow-[0_20px_70px_-10px_oklch(0.65_0.2_45/0.9)]">
                        Empezar a leer
                        <x-icon name="arrow-right" class="size-5 transition group-hover:translate-x-1" />
                    </a>
                </div>
            </div>

            <div class="relative mx-auto w-full max-w-md lg:max-w-none" style="perspective: 1400px">
                <div class="cine-line" style="--delay: 1200ms">
                    <div class="absolute inset-[12%] rounded-full bg-ember-500/30 blur-3xl"></div>
                    <div class="cine-float relative">
                        <div id="book" class="transition-transform duration-300 ease-out will-change-transform">
                            <img src="{{ asset('images/flash.webp') }}" alt="Portada de «{{ $title }}» de {{ $author }}" width="782" height="1170"
                                 class="relative mx-auto w-[min(80vw,26rem)] drop-shadow-[0_40px_60px_rgba(0,0,0,0.75)]">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <a href="#historia" class="cine-scroll-cue absolute bottom-8 left-1/2 z-10 flex -translate-x-1/2 flex-col items-center gap-2 text-[10px] tracking-[0.3em] text-paper/50 uppercase">
            Desliza
            <x-icon name="chevron-down" class="size-4" />
        </a>
    </section>

    {{-- Quote --}}
    <section id="historia" class="relative flex min-h-[90svh] items-center justify-center overflow-hidden px-6 py-32">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,oklch(0.22_0.04_55),transparent_65%)]"></div>
        <div class="relative max-w-5xl text-center">
            <p class="cine-reveal text-xs tracking-[0.4em] text-paper/40 uppercase">Prólogo</p>
            <blockquote class="cine-reveal mt-10 font-hand text-[clamp(3rem,8vw,7rem)] leading-none text-paper" style="--delay: 200ms">
                Empezar a veces duele.
            </blockquote>
            <div class="cine-reveal cine-horizon mx-auto mt-16 h-px w-2/3" style="--delay: 500ms"></div>
            <p class="cine-reveal mx-auto mt-16 max-w-2xl font-display text-2xl leading-relaxed font-light text-paper/75 italic sm:text-3xl" style="--delay: 700ms">
                Hay caídas que parecen definitivas. Este libro es el testimonio de que, incluso ahí, todavía hay un camino que empieza.
            </p>
        </div>
    </section>

    {{-- Three acts --}}
    <section id="actos" class="relative px-6 py-32 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="cine-reveal max-w-2xl">
                <p class="text-xs font-semibold tracking-[0.35em] text-ember-400 uppercase">El viaje</p>
                <h2 class="mt-5 font-display text-5xl font-light tracking-tight sm:text-6xl">Tres momentos que <em class="text-ember-300">cambian una vida</em>.</h2>
            </div>

            <div class="mt-24 grid gap-px overflow-hidden rounded-3xl bg-paper/10 ring-1 ring-paper/10 md:grid-cols-3">
                @foreach ([
                    ['I', 'Pérdida', 'Cuando lo que dabas por seguro desaparece y el suelo se abre bajo los pies.'],
                    ['II', 'Migración', 'Dejar atrás un lugar, una lengua de todos los días, una versión de uno mismo. Y caminar igual.'],
                    ['III', 'Renacimiento', 'Descubrir que el fondo no era el final, sino el punto exacto desde donde volver a empezar.'],
                ] as [$numeral, $act, $text])
                    <article class="cine-reveal group relative overflow-hidden bg-night p-10 transition duration-700 hover:bg-[oklch(0.17_0.02_55)] lg:p-12" style="--delay: {{ $loop->index * 200 }}ms">
                        <div class="absolute -right-10 -bottom-10 size-48 rounded-full bg-ember-500/0 blur-3xl transition duration-700 group-hover:bg-ember-500/20"></div>
                        <p class="cine-numeral font-display text-8xl font-semibold">{{ $numeral }}</p>
                        <h3 class="mt-10 font-display text-3xl font-medium">{{ $act }}</h3>
                        <p class="mt-4 leading-relaxed text-paper/60">{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Author --}}
    <section id="autor" class="relative overflow-hidden px-6 py-32 lg:px-10">
        <div class="absolute top-1/2 left-0 h-px w-full bg-gradient-to-r from-transparent via-paper/15 to-transparent"></div>
        <div class="relative mx-auto grid max-w-6xl items-center gap-16 lg:grid-cols-[1fr_1.2fr]">
            <div class="cine-reveal relative mx-auto w-64 sm:w-72">
                <div class="absolute inset-0 -rotate-6 rounded-2xl bg-ember-500/20 blur-2xl"></div>
                <img src="{{ asset('images/flash.webp') }}" alt="" class="relative -rotate-3 drop-shadow-[0_30px_40px_rgba(0,0,0,0.7)]" loading="lazy">
            </div>
            <div class="cine-reveal" style="--delay: 200ms">
                <p class="text-xs font-semibold tracking-[0.35em] text-ember-400 uppercase">Escrito por</p>
                <h2 class="mt-5 font-display text-5xl leading-tight font-light tracking-tight sm:text-6xl">{{ $author }}</h2>
                <p class="mt-8 max-w-lg text-lg leading-relaxed text-paper/65">
                    No es ficción. Es una historia real, contada desde adentro, para quien hoy está empezando de nuevo y necesita saber que no es el único.
                </p>
                <div class="mt-10 flex flex-wrap gap-3">
                    @foreach (['Historia real', 'Migración', 'Renacimiento'] as $tag)
                        <span class="rounded-full px-4 py-1.5 text-sm text-paper/70 ring-1 ring-paper/20">{{ $tag }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Call to action --}}
    <section id="leer" class="relative overflow-hidden px-6 py-40 text-center">
        <div class="cine-sun absolute bottom-[-30rem] left-1/2 size-[60rem] -translate-x-1/2 rounded-full opacity-70 blur-3xl"></div>
        <div class="cine-horizon absolute inset-x-0 bottom-[22%] h-px opacity-60"></div>
        <div class="relative mx-auto max-w-3xl">
            <h2 class="cine-reveal font-display text-[clamp(2.75rem,7vw,5.5rem)] leading-[1] font-light tracking-tight">
                Tu historia también puede <span class="font-semibold text-ember-300 italic">empezar de nuevo</span>.
            </h2>
            <div class="cine-reveal mt-12 flex flex-wrap items-center justify-center gap-4" style="--delay: 400ms">
                <a href="{{ $readUrl }}" class="group inline-flex items-center gap-3 rounded-full bg-paper px-9 py-4 text-base font-semibold text-night transition hover:bg-white">
                    Empezar a leer ahora <x-icon name="arrow-right" class="size-5 transition group-hover:translate-x-1" />
                </a>
            </div>
            <p class="cine-reveal mt-8 flex items-center justify-center gap-2 text-xs text-paper/40" style="--delay: 500ms"><x-icon name="lock" class="size-3.5" />Pago único · acceso de por vida · sin contraseñas</p>
        </div>
    </section>

    {{-- More books --}}
    @if ($books->isNotEmpty())
        <section class="border-t border-paper/10 px-6 py-24 lg:px-10">
            <div class="mx-auto max-w-7xl">
                <div class="cine-reveal flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.35em] text-paper/40 uppercase">Más libros</p>
                        <h2 class="mt-4 font-display text-3xl font-light">También en la biblioteca</h2>
                    </div>
                    <a href="{{ auth()->check() ? route('library') : route('login') }}" class="text-sm text-paper/60 transition hover:text-paper">Ver todo el catálogo →</a>
                </div>
                <div class="mt-12 grid grid-cols-3 gap-6 sm:grid-cols-6">
                    @foreach ($books as $book)
                        <a href="{{ auth()->check() ? route('library.show', $book) : route('login') }}" class="cine-reveal group opacity-80 transition hover:opacity-100" style="--delay: {{ $loop->index * 80 }}ms">
                            <x-book-cover :book="$book" class="ring-paper/10 transition duration-500 group-hover:-translate-y-2" />
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <footer class="border-t border-paper/10 px-6 py-10 text-sm text-paper/40 lg:px-10">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 sm:flex-row">
            <p><span class="font-display text-paper/70">{{ config('app.name') }}</span> · © {{ now()->year }}</p>
            <div class="flex gap-6">
                <a href="{{ route('book.start') }}" class="hover:text-paper">Comprar el libro</a>
                <a href="{{ auth()->check() ? route('library') : route('login') }}" class="hover:text-paper">{{ auth()->check() ? 'Biblioteca' : 'Ingresar' }}</a>
            </div>
        </div>
    </footer>

    <script>
        (() => {
            if (window.libroTranslation?.current() === 'en') {
                document.querySelectorAll('[data-en]').forEach((element) => {
                    const target = element.firstChild?.nodeType === Node.TEXT_NODE ? element.firstChild : element;
                    target.textContent = element.dataset.en + (target === element ? '' : ' ');
                });
            }

            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.18, rootMargin: '0px 0px -8% 0px' });
            document.querySelectorAll('.cine-reveal').forEach((element) => observer.observe(element));

            const nav = document.getElementById('nav');
            const updateNav = () => {
                const scrolled = window.scrollY > 40;
                nav.classList.toggle('backdrop-blur-xl', scrolled);
                nav.classList.toggle('bg-night/70', scrolled);
            };
            window.addEventListener('scroll', updateNav, { passive: true });
            updateNav();

            const book = document.getElementById('book');
            if (book && !reduceMotion && window.matchMedia('(pointer: fine)').matches) {
                window.addEventListener('mousemove', (event) => {
                    const x = (event.clientX / window.innerWidth - 0.5) * 2;
                    const y = (event.clientY / window.innerHeight - 0.5) * 2;
                    book.style.transform = `rotateY(${x * -9}deg) rotateX(${y * 6}deg) translateZ(0)`;
                });
            }
        })();
    </script>
</body>
</html>
