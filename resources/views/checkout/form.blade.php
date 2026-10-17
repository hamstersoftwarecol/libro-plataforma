<x-app-layout title="Pago" bare>
    <section class="relative overflow-hidden px-4 py-12 sm:px-6 lg:px-8">
        <div class="cine-sun absolute -top-40 right-[-10rem] size-[40rem] rounded-full opacity-50 blur-2xl"></div>
        <div class="cine-vignette pointer-events-none absolute inset-0"></div>

        <div class="relative mx-auto max-w-6xl">
            <x-alerts />

            <div class="mb-10">
                <p class="eyebrow">Paso final</p>
                <h1 class="page-title mt-3">Completa tu pago</h1>
                <p class="mt-2 text-zinc-400">Tu acceso al libro se activa en cuanto se confirme el pago.
                    @guest
                        ¿Ya lo compraste? <a href="{{ route('login') }}" class="link">Ingresa aquí</a>.
                    @endguest
                </p>
            </div>

            <form method="POST" action="{{ route('checkout.store') }}" class="grid gap-8 lg:grid-cols-[1fr_380px]" id="checkout-form">
                @csrf

                <div class="space-y-6">
                    <input type="hidden" name="book_id" value="{{ $book?->id }}">
                    @if (!empty($billing['billing_name']))
                        <input type="hidden" name="billing_name" value="{{ $billing['billing_name'] }}">
                    @endif
                    @if (!empty($billing['billing_document']))
                        <input type="hidden" name="billing_document" value="{{ $billing['billing_document'] }}">
                    @endif
                    @if (!empty($billing['billing_country']))
                        <input type="hidden" name="billing_country" value="{{ $billing['billing_country'] }}">
                    @endif
                    @if (!empty($billing['billing_phone']))
                        <input type="hidden" name="billing_phone" value="{{ $billing['billing_phone'] }}">
                    @endif
                    @error('book_id')
                        <div class="flex items-start gap-3 rounded-2xl bg-red-950/50 px-4 py-3.5 text-sm text-red-200 ring-1 ring-red-900"><x-icon name="warning" class="size-5 text-red-400" />{{ $message }}</div>
                    @enderror

                    {{-- Customer email --}}
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-brand-600 text-xs text-white">1</span>Tu cuenta de acceso</h2>
                        </div>
                        <div class="card-body">
                            @auth
                                <div>
                                    <label class="label">Correo electrónico</label>
                                    <input value="{{ auth()->user()->email }}" disabled class="input cursor-not-allowed opacity-60">
                                    <p class="hint mt-2">Tu cuenta ya está activa con este correo. Aquí recibirás el comprobante y el acceso inmediato.</p>
                                </div>
                            @else
                                <div>
                                    <label for="email" class="label">Correo electrónico *</label>
                                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="input" placeholder="tu@correo.com">
                                    @error('email')<p class="error">{{ $message }}</p>@enderror
                                    <p class="hint mt-2">Solo necesitamos tu correo para activar tu acceso al confirmar el pago. Sin contraseñas ni formularios largos.</p>
                                </div>
                            @endauth
                        </div>
                    </section>

                    {{-- Payment method --}}
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-brand-600 text-xs text-white">2</span>Pago seguro</h2>
                            <span class="flex items-center gap-1.5 text-xs text-emerald-400"><x-icon name="lock" class="size-3.5" />Cifrado</span>
                        </div>
                        <div class="card-body flex items-start gap-4">
                            <div class="grid size-11 shrink-0 place-items-center rounded-xl bg-white/5 text-brand-400 ring-1 ring-white/10"><x-icon name="card" /></div>
                            <p class="text-sm leading-relaxed text-zinc-400">
                                Al continuar te llevaremos a la <strong class="text-zinc-200">pasarela de pago segura de Bold</strong> para ingresar los datos de tu tarjeta o PSE.
                                Nunca guardamos datos bancarios. Al aprobarse el pago, volverás directamente a leer el libro.
                            </p>
                        </div>
                    </section>
                </div>

                {{-- Summary --}}
                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <div class="card overflow-hidden">
                        @if ($book)
                            <div class="relative flex items-center gap-5 bg-gradient-to-br from-brand-900/40 to-transparent p-6">
                                @if ($book->cover_path)
                                    <img src="{{ $book->coverUrl() }}" alt="" class="w-20 -rotate-3 rounded drop-shadow-[0_15px_25px_rgba(0,0,0,0.7)]">
                                @else
                                    <x-book-cover :book="$book" class="w-20 -rotate-3" />
                                @endif
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold tracking-[0.2em] text-brand-400 uppercase">Acceso de por vida</p>
                                    <p class="mt-1 font-display text-lg leading-snug">{{ $book->title }}</p>
                                    <p class="mt-1 truncate text-xs text-zinc-500">{{ $book->authorNames() }}</p>
                                </div>
                            </div>
                        @endif
                        <div class="card-body space-y-4 border-t border-white/[0.06]">
                            @if ($book && $book->isForSale())
                                <ul class="space-y-2.5 text-sm text-zinc-300">
                                    @foreach (['Pago único, sin renovaciones', 'Es tuyo para siempre', 'Lee en cualquier navegador', 'Tu progreso se guarda solo'] as $benefit)
                                        <li class="flex items-center gap-2.5"><span class="grid size-5 place-items-center rounded-full bg-brand-500/20 text-brand-300"><x-icon name="check" class="size-3" stroke="2.5" /></span>{{ $benefit }}</li>
                                    @endforeach
                                </ul>
                                <div class="flex items-end justify-between border-t border-white/[0.06] pt-4">
                                    <span class="text-sm text-zinc-400">Total · pago único</span>
                                    <span class="font-display text-3xl font-semibold tabular-nums">{{ $book->formattedPrice() }}</span>
                                </div>

                                <label class="flex items-start gap-3 rounded-xl bg-white/[0.03] p-3 text-xs leading-relaxed text-zinc-400">
                                    <input type="checkbox" name="terms" value="1" class="checkbox mt-0.5" @checked(old('terms', true)) required>
                                    <span>Acepto los términos y condiciones y entiendo que el acceso es personal y solo para lectura en línea.</span>
                                </label>
                                @error('terms')<p class="error">{{ $message }}</p>@enderror

                                <button type="submit" class="btn btn-primary w-full py-3.5 text-base cursor-pointer">
                                    <x-icon name="lock" class="size-4" />Pagar y empezar a leer
                                </button>
                            @else
                                <p class="text-sm text-zinc-400">Este libro todavía no está a la venta. Vuelve pronto.</p>
                            @endif
                        </div>
                    </div>
                    <p class="mt-4 flex items-center justify-center gap-2 text-xs text-zinc-500"><x-icon name="shield" class="size-4" />Pago seguro · no guardamos datos de tarjeta</p>
                </aside>
            </form>
        </div>
    </section>

    <script>
        document.getElementById('checkout-form').addEventListener('submit', (event) => {
            const button = event.target.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.innerHTML = 'Conectando con la pasarela…';
            }
        });
    </script>
</x-app-layout>
