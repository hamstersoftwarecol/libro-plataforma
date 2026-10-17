<!DOCTYPE html>
<html lang="es" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $book->title }} · {{ config('app.name') }}</title>
    <x-fonts />
    @vite(['resources/css/app.css'])
    <style>
        @media print { body { display: none !important; } }
        .reader-protected { -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; }
        .dark-pages #page-canvas { filter: invert(0.9) hue-rotate(180deg) sepia(0.28) brightness(0.92); }
        body[data-theme='dark'] { background-image: radial-gradient(ellipse 70% 45% at 50% -5%, oklch(0.3 0.07 45 / 0.5), transparent 70%), radial-gradient(ellipse 50% 35% at 50% 110%, oklch(0.26 0.06 35 / 0.35), transparent 70%); background-attachment: fixed; }
        body[data-theme='light'] { background-image: radial-gradient(ellipse 70% 45% at 50% -5%, oklch(0.9 0.06 70 / 0.7), transparent 70%); background-attachment: fixed; }
        #page-wrapper, #chapter-wrapper { position: relative; }
        body[data-theme='dark'] #page-wrapper::before, body[data-theme='dark'] #chapter-wrapper::before { content: ''; position: absolute; inset: 8% -6%; z-index: -1; border-radius: 9999px; background: oklch(0.6 0.17 45 / 0.16); filter: blur(60px); }
        #chapter { font-family: 'Fraunces', Georgia, serif; line-height: 1.8; }
        #chapter h1, #chapter h2, #chapter h3 { font-weight: 600; line-height: 1.3; margin: 1.5em 0 0.6em; }
        #chapter h1 { font-size: 1.8em; } #chapter h2 { font-size: 1.45em; } #chapter h3 { font-size: 1.2em; }
        #chapter p { margin: 0 0 1em; text-align: justify; }
        #chapter img { max-width: 100%; height: auto; margin: 1em auto; display: block; }
        #chapter blockquote { border-left: 3px solid currentColor; opacity: .85; padding-left: 1em; margin: 1em 0; }
    </style>
</head>
<body data-theme="dark" class="reader-protected h-full bg-[#efe6d6] text-zinc-900 transition-colors duration-500 data-[theme=dark]:bg-zinc-950 data-[theme=dark]:text-zinc-100">
    <header class="sticky top-0 z-20 border-b border-black/10 bg-[#f6efe3]/80 backdrop-blur-xl transition-colors duration-500 in-data-[theme=dark]:border-white/[0.06] in-data-[theme=dark]:bg-zinc-950/70">
        <div class="mx-auto flex h-14 max-w-6xl items-center gap-1 px-3 text-sm sm:gap-2">
            <a href="{{ route('library.show', $book) }}" class="btn btn-ghost btn-sm px-2" title="Salir del lector"><x-icon name="arrow-left" class="size-4" /></a>
            <div class="min-w-0 flex-1 px-1">
                <h1 class="truncate font-display text-base font-medium">{{ $book->title }}</h1>
                <p class="hidden truncate text-xs text-zinc-500 sm:block">{{ $book->authorNames() }}</p>
            </div>

            <div class="flex items-center gap-1 rounded-xl bg-black/[0.05] p-1 in-data-[theme=dark]:bg-white/[0.06]">
                <button type="button" data-action="prev" class="grid size-8 place-items-center rounded-lg hover:bg-white/70 disabled:opacity-30 in-data-[theme=dark]:hover:bg-white/10" title="Anterior (←)"><x-icon name="chevron-left" class="size-4" /></button>
                <label class="flex items-center gap-1 px-1 tabular-nums">
                    <input id="position-input" type="number" min="1" max="{{ $total }}" value="{{ $position }}"
                           class="w-12 rounded-md bg-transparent py-1 text-center font-semibold focus:bg-white/70 focus:outline-none in-data-[theme=dark]:focus:bg-white/10">
                    <span class="text-zinc-500">/ {{ $total }}</span>
                </label>
                <button type="button" data-action="next" class="grid size-8 place-items-center rounded-lg hover:bg-white/70 disabled:opacity-30 in-data-[theme=dark]:hover:bg-white/10" title="Siguiente (→)"><x-icon name="chevron-right" class="size-4" /></button>
            </div>

            <div class="hidden items-center gap-1 rounded-xl bg-black/[0.05] p-1 sm:flex in-data-[theme=dark]:bg-white/[0.06]">
                <button type="button" data-action="zoom-out" class="grid size-8 place-items-center rounded-lg hover:bg-white/70 in-data-[theme=dark]:hover:bg-white/10" title="Reducir"><x-icon name="zoom-out" class="size-4" /></button>
                <span id="zoom-label" class="w-11 text-center text-xs font-semibold tabular-nums text-zinc-500">100%</span>
                <button type="button" data-action="zoom-in" class="grid size-8 place-items-center rounded-lg hover:bg-white/70 in-data-[theme=dark]:hover:bg-white/10" title="Ampliar"><x-icon name="zoom-in" class="size-4" /></button>
            </div>
            @if ($book->file_format === \App\Enums\BookFormat::Pdf)
                <button type="button" data-action="text-mode" class="hidden h-10 place-items-center rounded-xl px-2.5 text-xs font-semibold hover:bg-black/[0.05] sm:grid in-data-[theme=dark]:hover:bg-white/10" title="Alternar entre página original y solo texto (traducible)">
                    <span id="text-mode-label" class="notranslate" translate="no">Texto</span>
                </button>
            @endif
            <x-language-switcher class="hidden sm:flex" />
            <button type="button" data-action="theme" class="grid size-10 place-items-center rounded-xl hover:bg-black/[0.05] in-data-[theme=dark]:hover:bg-white/10" title="Cambiar entre noche y papel"><x-icon name="moon" class="size-4" /></button>
        </div>
        <div class="h-0.5 bg-transparent"><div id="progress-bar" class="h-full bg-gradient-to-r from-brand-400 to-brand-700 transition-all duration-300" style="width: 0"></div></div>
    </header>

    <main class="px-2 py-8">
        @if ($book->file_format === \App\Enums\BookFormat::Pdf)
            <div id="page-wrapper" class="mx-auto transition-[max-width]" style="max-width: 900px">
                <canvas id="page-canvas" class="block w-full rounded-md bg-white shadow-[0_30px_80px_-20px_rgba(0,0,0,0.6)] ring-1 ring-black/10 transition-[filter] duration-500"></canvas>
            </div>
            <div id="chapter-wrapper" class="relative mx-auto hidden max-w-2xl overflow-hidden rounded-2xl bg-[#fbf6ec] px-6 py-12 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.5)] ring-1 ring-black/10 transition-colors duration-500 sm:px-14 in-data-[theme=dark]:bg-zinc-900 in-data-[theme=dark]:text-zinc-200 in-data-[theme=dark]:ring-white/[0.06]">
                <div id="watermark" class="pointer-events-none absolute inset-0 opacity-[0.07]"></div>
                <p class="notranslate mb-8 flex items-center gap-2 text-xs text-zinc-500" translate="no" id="text-mode-note"></p>
                <article id="chapter" class="relative"></article>
            </div>
        @else
            <div id="chapter-wrapper" class="relative mx-auto max-w-2xl overflow-hidden rounded-2xl bg-[#fbf6ec] px-6 py-12 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.5)] ring-1 ring-black/10 transition-colors duration-500 sm:px-14 in-data-[theme=dark]:bg-zinc-900 in-data-[theme=dark]:text-zinc-200 in-data-[theme=dark]:ring-white/[0.06]">
                <div id="watermark" class="pointer-events-none absolute inset-0 opacity-[0.07]"></div>
                <article id="chapter" class="relative"></article>
            </div>
        @endif

        <div id="loading" class="mt-10 flex items-center justify-center gap-2 text-sm text-zinc-500">
            <span class="size-4 animate-spin rounded-full border-2 border-zinc-300 border-t-brand-600"></span>Cargando…
        </div>
    </main>

    <div id="blocked" class="fixed inset-0 z-30 hidden items-center justify-center bg-zinc-950/70 p-6 backdrop-blur-sm">
        <div class="max-w-md rounded-3xl bg-zinc-900 p-8 text-center text-zinc-100 shadow-2xl ring-1 ring-white/10 animate-fade-up">
            <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-brand-500/15 text-brand-400"><x-icon name="warning" /></div>
            <p id="blocked-message" class="mt-4 text-lg leading-relaxed font-medium"></p>
            <a href="{{ route('library.show', $book) }}" class="btn btn-primary mt-6">Volver al libro</a>
        </div>
    </div>

    <script>
        (() => {
            const config = {
                format: @js($book->file_format->value),
                total: @js($total),
                token: @js($sessionToken),
                widths: @js($pageWidths),
                pageUrl: @js(route('reader.page', [$book, '__N__'])),
                chapterUrl: @js(route('reader.chapter', [$book, '__N__'])),
                textUrl: @js(route('reader.text', [$book, '__N__'])),
                progressUrl: @js(route('reader.progress', $book)),
                storageKey: 'libro-reader-prefs',
            };

            const state = { position: @js($position), zoom: 1, theme: 'dark', loading: false, pendingRender: false, prefetched: new Map() };
            const $ = (selector) => document.querySelector(selector);
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            try {
                Object.assign(state, JSON.parse(localStorage.getItem(config.storageKey) || '{}'));
            } catch (error) {}

            delete state.textMode;
            const translating = () => (window.libroTranslation?.current() || 'es') !== 'es';
            state.textMode = config.format === 'epub' || translating();

            const savePrefs = () => {
                try { localStorage.setItem(config.storageKey, JSON.stringify({ zoom: state.zoom, theme: state.theme })); } catch (error) {}
            };

            const block = (message) => {
                $('#blocked-message').textContent = message;
                $('#blocked').classList.replace('hidden', 'flex');
            };

            const request = async (url, options = {}) => {
                const response = await fetch(url, {
                    ...options,
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'X-Reading-Session': config.token, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json, image/webp, image/*, */*', ...(options.headers || {}) },
                });

                if ([401, 403, 409, 419].includes(response.status)) {
                    const body = await response.json().catch(() => ({}));
                    block(body.message || 'Tu sesión expiró. Vuelve a ingresar.');
                    throw new Error('blocked');
                }

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response;
            };

            const pageWidth = () => {
                const needed = Math.min(window.innerWidth, 900 * state.zoom) * (window.devicePixelRatio || 1);
                return config.widths.find((width) => width >= needed) ?? config.widths.at(-1);
            };

            const fetchPage = (number) => {
                const key = number + '@' + pageWidth();

                if (!state.prefetched.has(key)) {
                    const promise = request(config.pageUrl.replace('__N__', number) + '?w=' + pageWidth())
                        .then((response) => response.blob())
                        .then((blob) => createImageBitmap(blob));
                    promise.catch(() => state.prefetched.delete(key));
                    state.prefetched.set(key, promise);
                }

                return state.prefetched.get(key);
            };

            const renderPdf = async () => {
                let bitmap;
                try {
                    bitmap = await fetchPage(state.position);
                } catch (error) {
                    if (error.message === 'blocked') throw error;
                    console.warn('Fallback a modo texto para la página:', state.position, error);
                    state.textMode = true;
                    const pdfWrapper = document.getElementById('page-wrapper');
                    if (pdfWrapper) {
                        pdfWrapper.classList.add('hidden');
                        $('#chapter-wrapper').classList.remove('hidden');
                        $('#text-mode-label').textContent = translating() ? 'Page' : 'Página';
                    }
                    await renderText();
                    return;
                }

                const canvas = $('#page-canvas');
                canvas.width = bitmap.width;
                canvas.height = bitmap.height;
                canvas.getContext('2d').drawImage(bitmap, 0, 0);
                $('#page-wrapper').style.maxWidth = (900 * state.zoom) + 'px';

                if (state.position < config.total) {
                    fetchPage(state.position + 1).catch(() => {});
                }

                for (const key of state.prefetched.keys()) {
                    const page = parseInt(key, 10);
                    if (page < state.position - 1 || page > state.position + 1) state.prefetched.delete(key);
                }
            };

            const watermarkBackground = (text) => {
                const escaped = text.replace(/[<>&'"]/g, (character) => '&#' + character.charCodeAt(0) + ';');
                const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="420" height="220"><text x="10" y="120" transform="rotate(-25 210 110)" font-family="sans-serif" font-size="16" fill="currentColor">${escaped}</text></svg>`;
                return `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`;
            };

            const renderText = async () => {
                const url = config.format === 'epub' ? config.chapterUrl : config.textUrl;
                const response = await request(url.replace('__N__', state.position));
                const data = await response.json();
                $('#chapter').innerHTML = data.html;
                $('#chapter').style.fontSize = (1.1 * state.zoom) + 'rem';
                const watermark = $('#watermark');
                watermark.style.backgroundImage = watermarkBackground(data.watermark);
                watermark.style.color = state.theme === 'dark' ? '#fff' : '#000';
                window.scrollTo({ top: 0 });
            };

            let saveTimer;
            const saveProgress = () => {
                clearTimeout(saveTimer);
                saveTimer = setTimeout(() => {
                    request(config.progressUrl, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ position: state.position }),
                    }).catch(() => {});
                }, 800);
            };

            const render = async () => {
                if (state.loading) {
                    state.pendingRender = true;
                    return;
                }
                state.loading = true;
                state.pendingRender = false;
                $('#loading').classList.replace('hidden', 'flex');

                try {
                    const pdfWrapper = document.getElementById('page-wrapper');
                    if (pdfWrapper) {
                        pdfWrapper.classList.toggle('hidden', state.textMode);
                        $('#chapter-wrapper').classList.toggle('hidden', ! state.textMode);
                        $('#text-mode-label').textContent = translating() ? (state.textMode ? 'Page' : 'Text') : (state.textMode ? 'Página' : 'Texto');
                        $('#text-mode-note').textContent = translating()
                            ? 'Automatic translation by Google · the original page layout is only available in Spanish.'
                            : 'Vista de texto de la página. Pulsa «Página» para volver al diseño original.';
                    }
                    state.textMode ? await renderText() : await renderPdf();
                    $('#loading').classList.replace('flex', 'hidden');
                } catch (error) {
                    if (error.message !== 'blocked') $('#loading').textContent = 'No se pudo cargar. Intenta de nuevo.';
                } finally {
                    state.loading = false;
                }

                $('#position-input').value = state.position;
                $('#progress-bar').style.width = (state.position / config.total * 100) + '%';
                $('#zoom-label').textContent = Math.round(state.zoom * 100) + '%';
                document.querySelector('[data-action="prev"]').disabled = state.position <= 1;
                document.querySelector('[data-action="next"]').disabled = state.position >= config.total;
                document.body.dataset.theme = state.theme;
                document.documentElement.classList.toggle('dark', state.theme === 'dark');
                document.body.classList.toggle('dark-pages', state.theme === 'dark');

                if (state.pendingRender) render();
            };

            const goTo = (position) => {
                const target = Math.min(Math.max(1, position), config.total);
                if (target === state.position) return render();
                state.position = target;
                if (config.format === 'pdf') window.scrollTo({ top: 0 });
                render();
                saveProgress();
            };

            const actions = {
                prev: () => goTo(state.position - 1),
                next: () => goTo(state.position + 1),
                'zoom-in': () => { state.zoom = Math.min(2, +(state.zoom + 0.1).toFixed(1)); savePrefs(); render(); },
                'zoom-out': () => { state.zoom = Math.max(0.6, +(state.zoom - 0.1).toFixed(1)); savePrefs(); render(); },
                'text-mode': () => { state.textMode = ! state.textMode; savePrefs(); render(); },
                theme: () => { state.theme = state.theme === 'dark' ? 'light' : 'dark'; savePrefs(); render(); },
            };

            document.querySelectorAll('[data-action]').forEach((button) => button.addEventListener('click', () => actions[button.dataset.action]()));
            $('#position-input').addEventListener('change', (event) => goTo(parseInt(event.target.value, 10) || 1));

            document.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && ['s', 'p', 'c', 'a', 'u'].includes(event.key.toLowerCase())) {
                    event.preventDefault();
                    return;
                }
                if (event.target.tagName === 'INPUT') return;
                if (event.key === 'ArrowRight' || event.key === 'PageDown') actions.next();
                if (event.key === 'ArrowLeft' || event.key === 'PageUp') actions.prev();
            });

            ['contextmenu', 'dragstart', 'copy', 'cut', 'selectstart'].forEach((type) =>
                document.addEventListener(type, (event) => { if (event.target.tagName !== 'INPUT') event.preventDefault(); }));

            let resizeTimer;
            window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(() => config.format === 'pdf' && render(), 300); });

            setInterval(() => {
                if (document.visibilityState === 'visible') saveProgress();
            }, 60000);

            render();
            saveProgress();
        })();
    </script>
</body>
</html>
