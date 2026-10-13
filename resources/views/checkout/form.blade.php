@php
    $countries = ['CO' => 'Colombia', 'MX' => 'México', 'AR' => 'Argentina', 'CL' => 'Chile', 'PE' => 'Perú', 'EC' => 'Ecuador', 'VE' => 'Venezuela', 'BO' => 'Bolivia', 'PY' => 'Paraguay', 'UY' => 'Uruguay', 'CR' => 'Costa Rica', 'PA' => 'Panamá', 'GT' => 'Guatemala', 'HN' => 'Honduras', 'SV' => 'El Salvador', 'NI' => 'Nicaragua', 'DO' => 'República Dominicana', 'PR' => 'Puerto Rico', 'CU' => 'Cuba', 'ES' => 'España', 'US' => 'Estados Unidos', 'CA' => 'Canadá', 'BR' => 'Brasil', 'IT' => 'Italia', 'FR' => 'Francia', 'DE' => 'Alemania', 'GB' => 'Reino Unido', 'PT' => 'Portugal'];
@endphp

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
                    @error('book_id')
                        <div class="flex items-start gap-3 rounded-2xl bg-red-950/50 px-4 py-3.5 text-sm text-red-200 ring-1 ring-red-900"><x-icon name="warning" class="size-5 text-red-400" />{{ $message }}</div>
                    @enderror

                    {{-- Billing --}}
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-brand-600 text-xs text-white">1</span>Tus datos</h2>
                        </div>
                        <div class="card-body grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="billing_name" class="label">Nombre completo *</label>
                                <input id="billing_name" name="billing_name" value="{{ old('billing_name', $billing['billing_name']) }}" required autocomplete="name" class="input" placeholder="Como aparece en tu documento">
                                @error('billing_name')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            @auth
                                <div class="sm:col-span-2">
                                    <label class="label">Correo electrónico</label>
                                    <input value="{{ auth()->user()->email }}" disabled class="input cursor-not-allowed opacity-60">
                                    <p class="hint">Aquí recibirás el recibo.</p>
                                </div>
                            @else
                                <div>
                                    <label for="email" class="label">Correo electrónico *</label>
                                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="input" placeholder="tu@correo.com">
                                    @error('email')<p class="error">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="email_confirmation" class="label">Confirma tu correo *</label>
                                    <input id="email_confirmation" name="email_confirmation" type="email" value="{{ old('email_confirmation') }}" required autocomplete="email" class="input" placeholder="tu@correo.com" onpaste="return false">
                                </div>
                                <p class="hint -mt-2 sm:col-span-2">Con este correo se crea tu cuenta al confirmarse el pago y con él ingresarás después. Revisa que esté bien escrito.</p>
                            @endauth
                            <div>
                                <label for="billing_document" class="label">Documento de identidad</label>
                                <input id="billing_document" name="billing_document" value="{{ old('billing_document', $billing['billing_document']) }}" maxlength="30" class="input" placeholder="Opcional">
                                @error('billing_document')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="billing_country" class="label">País *</label>
                                <select id="billing_country" name="billing_country" required autocomplete="country" class="input">
                                    @foreach ($countries as $code => $country)
                                        <option value="{{ $code }}" @selected(old('billing_country', $billing['billing_country']) === $code)>{{ $country }}</option>
                                    @endforeach
                                </select>
                                @error('billing_country')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="billing_phone" class="label">Teléfono</label>
                                <input id="billing_phone" name="billing_phone" type="tel" value="{{ old('billing_phone', $billing['billing_phone']) }}" autocomplete="tel" class="input" placeholder="Opcional">
                                @error('billing_phone')<p class="error">{{ $message }}</p>@enderror
                            </div>
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
                                Al continuar te llevaremos a la <strong class="text-zinc-200">pasarela de pago segura</strong> para ingresar los datos de tu tarjeta.
                                Nunca guardamos ni vemos tu número de tarjeta. Al aprobarse, volverás directamente al libro.
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

                                <button type="submit" class="btn btn-primary w-full py-3.5 text-base">
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
