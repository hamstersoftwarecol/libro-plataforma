<x-app-layout title="Planes" bare>
    <section class="relative overflow-hidden">
        <div class="bg-grid absolute inset-0 [mask-image:radial-gradient(ellipse_at_top,black_20%,transparent_65%)]"></div>
        <div class="absolute -top-32 left-1/2 h-96 w-[800px] -translate-x-1/2 rounded-full bg-gradient-to-r from-brand-400/20 to-brand-700/20 blur-3xl dark:from-brand-700/25 dark:to-brand-900/20"></div>

        <div class="relative mx-auto max-w-5xl px-4 pt-20 pb-24 sm:px-6 lg:px-8">
            <x-alerts />
            <div class="mx-auto max-w-2xl text-center animate-fade-up">
                <p class="eyebrow">Planes</p>
                <h1 class="mt-3 font-display text-5xl font-semibold tracking-tight text-balance">Un precio. Todos los libros.</h1>
                <p class="mt-5 text-lg text-zinc-600 dark:text-zinc-400">Lee sin límites desde tu navegador. Sin permanencia: cancelas cuando quieras.</p>
            </div>

            @if ($currentSubscription)
                <div class="mx-auto mt-10 flex max-w-xl items-center gap-3 rounded-2xl bg-emerald-50 px-5 py-4 text-sm text-emerald-900 ring-1 ring-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-200 dark:ring-emerald-900">
                    <x-icon name="check-circle" class="size-5 text-emerald-600" />
                    <p>Tienes acceso hasta el <strong>{{ $currentSubscription->ends_at->translatedFormat('j \d\e F \d\e Y') }}</strong>. Si pagas otro periodo, se suma al tiempo que te queda.</p>
                </div>
            @endif

            @php
                $monthly = $plans->firstWhere('interval', \App\Enums\PlanInterval::Monthly);
                $featuredId = $plans->firstWhere('interval', \App\Enums\PlanInterval::Yearly)?->id;
            @endphp

            <div class="mx-auto mt-14 grid max-w-4xl gap-6 {{ [1 => 'md:grid-cols-1 max-w-md', 2 => 'md:grid-cols-2'][$plans->count()] ?? 'md:grid-cols-3' }}">
                @forelse ($plans as $plan)
                    @php
                        $featured = $plan->id === $featuredId;
                        $savings = $featured && $monthly && $monthly->currency === $plan->currency && $monthly->price * 12 > $plan->price
                            ? round((1 - $plan->price / ($monthly->price * 12)) * 100) : null;
                    @endphp
                    <div @class([
                        'relative flex flex-col rounded-3xl p-8 transition animate-fade-up',
                        'bg-zinc-950 text-white shadow-2xl ring-1 ring-zinc-800 dark:bg-brand-950 dark:ring-brand-800' => $featured,
                        'card hover:shadow-lift' => ! $featured,
                    ])>
                        @if ($featured)
                            <div class="absolute -top-px right-8 left-8 h-px bg-gradient-to-r from-transparent via-brand-400 to-transparent"></div>
                            <span class="absolute -top-3 right-8 rounded-full bg-gradient-to-r from-brand-400 to-brand-700 px-3 py-1 text-xs font-semibold text-white shadow-lg">{{ $savings ? "Ahorra {$savings}%" : 'Recomendado' }}</span>
                        @endif

                        <h2 class="text-lg font-semibold">{{ $plan->name }}</h2>
                        <p @class(['mt-1 text-sm', 'text-zinc-400' => $featured, 'text-zinc-500' => ! $featured])>{{ $plan->description ?: $plan->interval->label() }}</p>

                        <p class="mt-8 flex items-baseline gap-1.5">
                            <span class="font-display text-5xl font-semibold tracking-tight tabular-nums">{{ $plan->formattedPrice() }}</span>
                            <span @class(['text-sm', 'text-zinc-400' => $featured, 'text-zinc-500' => ! $featured])>{{ $plan->interval->perLabel() }}</span>
                        </p>

                        <ul class="mt-8 flex-1 space-y-3 text-sm">
                            @foreach (['Todo el catálogo, sin límites', 'Lectura en cualquier navegador', 'Progreso guardado automáticamente', 'Cancela cuando quieras'] as $feature)
                                <li class="flex items-center gap-3">
                                    <span @class(['grid size-5 place-items-center rounded-full', 'bg-brand-500/20 text-brand-300' => $featured, 'bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-300' => ! $featured])><x-icon name="check" class="size-3" stroke="2.5" /></span>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ route('checkout.create', ['plan' => $plan->slug]) }}" @class(['btn mt-10 w-full py-3', 'bg-white text-zinc-900 hover:bg-zinc-100' => $featured, 'btn-primary' => ! $featured])>{{ $currentSubscription ? 'Extender con este plan' : 'Suscribirme' }}</a>
                    </div>
                @empty
                    <x-empty-state icon="sparkles" title="Pronto publicaremos nuestros planes" class="card md:col-span-3" />
                @endforelse
            </div>

            <p class="mt-10 flex items-center justify-center gap-2 text-sm text-zinc-500"><x-icon name="shield" class="size-4" />Pagos procesados de forma segura. No guardamos datos de tarjeta.</p>
        </div>
    </section>
</x-app-layout>
