@php
    $countries = ['CO' => 'Colombia', 'MX' => 'México', 'AR' => 'Argentina', 'CL' => 'Chile', 'PE' => 'Perú', 'EC' => 'Ecuador', 'VE' => 'Venezuela', 'BO' => 'Bolivia', 'PY' => 'Paraguay', 'UY' => 'Uruguay', 'CR' => 'Costa Rica', 'PA' => 'Panamá', 'GT' => 'Guatemala', 'HN' => 'Honduras', 'SV' => 'El Salvador', 'NI' => 'Nicaragua', 'DO' => 'República Dominicana', 'PR' => 'Puerto Rico', 'CU' => 'Cuba', 'ES' => 'España', 'US' => 'Estados Unidos', 'CA' => 'Canadá', 'BR' => 'Brasil', 'IT' => 'Italia', 'FR' => 'Francia', 'DE' => 'Alemania', 'GB' => 'Reino Unido', 'PT' => 'Portugal'];
    $monthly = $plans->firstWhere('interval', \App\Enums\PlanInterval::Monthly);
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
                <p class="mt-2 text-zinc-400">Tu acceso al libro se activa en cuanto se confirme el pago.</p>
            </div>

            <form method="POST" action="{{ route('checkout.store') }}" class="grid gap-8 lg:grid-cols-[1fr_380px]" id="checkout-form">
                @csrf

                <div class="space-y-6">
                    {{-- Plan --}}
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-brand-600 text-xs text-white">1</span>Elige tu plan</h2>
                        </div>
                        <div class="card-body grid gap-3 sm:grid-cols-2">
                            @forelse ($plans as $plan)
                                @php
                                    $savings = $plan->interval === \App\Enums\PlanInterval::Yearly && $monthly && $monthly->currency === $plan->currency && $monthly->price * 12 > $plan->price
                                        ? round((1 - $plan->price / ($monthly->price * 12)) * 100) : null;
                                @endphp
                                <label class="group relative cursor-pointer rounded-2xl p-5 ring-1 ring-white/10 transition hover:ring-brand-500/60 has-[:checked]:bg-brand-500/10 has-[:checked]:ring-2 has-[:checked]:ring-brand-500">
                                    <input type="radio" name="plan_id" value="{{ $plan->id }}" class="peer sr-only" data-price="{{ $plan->formattedPrice() }}" data-name="{{ $plan->name }}" data-per="{{ $plan->interval->perLabel() }}" @checked($selectedPlanId === $plan->id) required>
                                    @if ($savings)
                                        <span class="absolute -top-2.5 right-4 rounded-full bg-gradient-to-r from-brand-400 to-brand-700 px-2.5 py-0.5 text-[11px] font-semibold text-white">Ahorra {{ $savings }}%</span>
                                    @endif
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold">{{ $plan->name }}</p>
                                            <p class="mt-0.5 text-xs text-zinc-500">{{ $plan->description ?: $plan->interval->label() }}</p>
                                        </div>
                                        <span class="grid size-5 shrink-0 place-items-center rounded-full ring-2 ring-zinc-600 transition peer-checked:bg-brand-500 peer-checked:ring-brand-500">
                                            <x-icon name="check" class="size-3 text-white opacity-0 transition group-has-[:checked]:opacity-100" stroke="3" />
                                        </span>
                                    </div>
                                    <p class="mt-4"><span class="font-display text-3xl font-semibold tabular-nums">{{ $plan->formattedPrice() }}</span> <span class="text-sm text-zinc-500">{{ $plan->interval->perLabel() }}</span></p>
                                </label>
                            @empty
                                <p class="text-sm text-zinc-500 sm:col-span-2">No hay planes disponibles en este momento.</p>
                            @endforelse
                            @error('plan_id')<p class="error sm:col-span-2">{{ $message }}</p>@enderror
                        </div>
                    </section>

                    {{-- Billing --}}
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-brand-600 text-xs text-white">2</span>Datos de facturación</h2>
                        </div>
                        <div class="card-body grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="billing_name" class="label">Nombre completo *</label>
                                <input id="billing_name" name="billing_name" value="{{ old('billing_name', $billing['billing_name']) }}" required autocomplete="name" class="input" placeholder="Como aparece en tu documento">
                                @error('billing_name')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Correo electrónico</label>
                                <input value="{{ auth()->user()->email }}" disabled class="input cursor-not-allowed opacity-60">
                                <p class="hint">Aquí recibirás el recibo.</p>
                            </div>
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
                            <h2 class="card-title flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-brand-600 text-xs text-white">3</span>Pago seguro</h2>
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
                                <img src="{{ asset('images/flash.webp') }}" alt="" class="w-20 -rotate-3 drop-shadow-[0_15px_25px_rgba(0,0,0,0.7)]">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold tracking-[0.2em] text-brand-400 uppercase">Acceso inmediato</p>
                                    <p class="mt-1 font-display text-lg leading-snug">{{ $book->title }}</p>
                                    <p class="mt-1 truncate text-xs text-zinc-500">{{ $book->authorNames() }}</p>
                                </div>
                            </div>
                        @endif
                        <div class="card-body space-y-4 border-t border-white/[0.06]">
                            <div class="flex justify-between text-sm"><span class="text-zinc-400">Plan</span><span class="font-medium" id="summary-plan">—</span></div>
                            <div class="flex justify-between text-sm"><span class="text-zinc-400">Incluye</span><span class="text-right">Todo el catálogo</span></div>
                            <div class="flex items-end justify-between border-t border-white/[0.06] pt-4">
                                <span class="text-sm text-zinc-400">Total</span>
                                <span><span class="font-display text-3xl font-semibold tabular-nums" id="summary-price">—</span> <span class="text-xs text-zinc-500" id="summary-per"></span></span>
                            </div>

                            <label class="flex items-start gap-3 rounded-xl bg-white/[0.03] p-3 text-xs leading-relaxed text-zinc-400">
                                <input type="checkbox" name="terms" value="1" class="checkbox mt-0.5" @checked(old('terms')) required>
                                <span>Acepto los términos y condiciones y entiendo que el acceso es personal, solo para lectura en línea y se renueva hasta que lo cancele.</span>
                            </label>
                            @error('terms')<p class="error">{{ $message }}</p>@enderror

                            <button type="submit" class="btn btn-primary w-full py-3.5 text-base" @disabled($plans->isEmpty())>
                                <x-icon name="lock" class="size-4" />Pagar y empezar a leer
                            </button>
                            @if ($currentSubscription)
                                <p class="text-center text-xs text-emerald-400">Ya tienes acceso hasta el {{ $currentSubscription->ends_at->translatedFormat('j \d\e F') }}; este pago lo extiende.</p>
                            @endif
                        </div>
                    </div>
                    <p class="mt-4 flex items-center justify-center gap-2 text-xs text-zinc-500"><x-icon name="shield" class="size-4" />Cancela cuando quieras desde Mi cuenta</p>
                </aside>
            </form>
        </div>
    </section>

    <script>
        (() => {
            const update = () => {
                const selected = document.querySelector('input[name="plan_id"]:checked');
                if (! selected) return;
                document.getElementById('summary-plan').textContent = selected.dataset.name;
                document.getElementById('summary-price').textContent = selected.dataset.price;
                document.getElementById('summary-per').textContent = selected.dataset.per;
            };
            document.querySelectorAll('input[name="plan_id"]').forEach((input) => input.addEventListener('change', update));
            update();
            document.getElementById('checkout-form').addEventListener('submit', (event) => {
                const button = event.target.querySelector('button[type="submit"]');
                button.disabled = true;
                button.innerHTML = 'Conectando con la pasarela…';
            });
        })();
    </script>
</x-app-layout>
