<x-app-layout title="Pagar con Bold" bare>
    <section class="relative flex min-h-[calc(100vh-4rem)] items-center overflow-hidden px-4 py-16 sm:px-6 lg:px-8">
        <div class="cine-sun absolute top-1/2 left-1/2 size-[44rem] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-40 blur-2xl"></div>
        <div class="cine-vignette pointer-events-none absolute inset-0"></div>

        <div class="relative mx-auto w-full max-w-lg animate-fade-up">
            <div class="card overflow-hidden shadow-lift">
                @if ($book)
                    <div class="flex items-center gap-5 bg-gradient-to-br from-brand-900/40 to-transparent p-6">
                        @if ($book->cover_path)
                            <img src="{{ $book->coverUrl() }}" alt="" class="w-16 -rotate-3 rounded drop-shadow-[0_15px_25px_rgba(0,0,0,0.7)]">
                        @else
                            <x-book-cover :book="$book" class="w-16 -rotate-3" />
                        @endif
                        <div>
                            <p class="text-[11px] font-semibold tracking-[0.2em] text-brand-400 uppercase">Acceso inmediato</p>
                            <p class="mt-1 font-display text-lg leading-snug">{{ $book->title }}</p>
                        </div>
                    </div>
                @endif

                <div class="card-body space-y-6 border-t border-white/[0.06]">
                    <div class="flex items-end justify-between">
                        <div>
                            <p class="text-sm text-zinc-400">Pago único · acceso de por vida</p>
                            <p class="font-display text-4xl font-semibold tabular-nums">{{ $payment->formattedAmount() }}</p>
                        </div>
                    </div>

                    @if ($testMode)
                        <div class="flex items-start gap-2 rounded-xl bg-amber-500/10 px-3.5 py-2.5 text-xs text-amber-300 ring-1 ring-amber-500/30">
                            <x-icon name="info" class="size-4" />Bold en modo de pruebas: no se realizará un cobro real.
                        </div>
                    @endif

                    <div class="flex min-h-14 flex-col items-center justify-center gap-3" id="bold-button-container">
                        {{-- Bold's library is loaded by the button script itself, so it is always available before the button renders. --}}
                        <script src="{{ $scriptUrl }}" data-bold-button="dark-L"
                            @foreach ($button as $attribute => $value)
                                data-{{ $attribute }}="{{ $value }}"
                            @endforeach
                        ></script>
                        <p id="bold-loading" class="flex items-center gap-2 text-sm text-zinc-400">
                            <span class="size-4 animate-spin rounded-full border-2 border-zinc-600 border-t-brand-500"></span>Cargando la pasarela de Bold…
                        </p>
                    </div>
                    <div id="bold-help" class="hidden rounded-xl bg-amber-500/10 p-4 text-xs leading-relaxed text-amber-200 ring-1 ring-amber-500/30">
                        <p class="font-semibold">¿No aparece el botón de pago?</p>
                        <p class="mt-1">Desactiva el bloqueador de anuncios para este sitio y <button type="button" class="underline" onclick="location.reload()">recarga la página</button>. Si continúa, escríbenos indicando la referencia {{ $payment->reference }}.</p>
                    </div>

                    <p class="flex items-center justify-center gap-2 text-center text-xs text-zinc-500">
                        <x-icon name="lock" class="size-3.5" />El pago se procesa en la pasarela segura de Bold. Al terminar volverás al libro.
                    </p>
                </div>
            </div>

            <a href="{{ route('checkout.create', array_filter(['libro' => $book?->slug])) }}" class="mt-6 flex items-center justify-center gap-1.5 text-sm text-zinc-500 hover:text-zinc-200"><x-icon name="arrow-left" class="size-4" />Cambiar plan o datos</a>
        </div>
    </section>

    <script>
        (() => {
            const container = document.getElementById('bold-button-container');
            const rendered = () => container.querySelector('bold-payment-button, iframe, button');
            const done = () => document.getElementById('bold-loading')?.remove();
            const observer = new MutationObserver(() => { if (rendered()) { done(); observer.disconnect(); } });
            observer.observe(container, { childList: true, subtree: true });
            setTimeout(() => {
                if (! rendered()) {
                    done();
                    document.getElementById('bold-help').classList.remove('hidden');
                }
            }, 8000);
        })();
    </script>
</x-app-layout>
