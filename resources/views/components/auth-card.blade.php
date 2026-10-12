@props(['title', 'subtitle' => null, 'icon' => 'mail'])

<x-app-layout :title="$title" bare>
    <section class="relative flex min-h-[calc(100vh-4rem)] items-center overflow-hidden px-4 py-16 sm:px-6 lg:px-8">
        <div class="cine-sun absolute top-1/2 left-[-14rem] size-[42rem] -translate-y-1/2 rounded-full opacity-70 blur-2xl lg:left-[2%]"></div>
        <div class="cine-vignette pointer-events-none absolute inset-0"></div>

        <div class="relative mx-auto grid w-full max-w-6xl items-center gap-16 lg:grid-cols-[1fr_440px]">
            <div class="hidden lg:block">
                <div class="relative mx-auto w-72 cine-float">
                    <div class="absolute inset-[10%] rounded-full bg-brand-500/30 blur-3xl"></div>
                    <img src="{{ asset('images/flash.webp') }}" alt="" class="relative -rotate-3 drop-shadow-[0_40px_60px_rgba(0,0,0,0.8)]">
                </div>
                <p class="mt-12 text-center font-hand text-4xl text-zinc-200/90">Empezar a veces duele.</p>
                <p class="mt-2 text-center text-xs tracking-[0.3em] text-zinc-500 uppercase">Pero el fondo no es el final</p>
            </div>

            <div class="w-full animate-fade-up">
                <div class="card p-8 shadow-lift sm:p-10">
                    <div class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-[var(--shadow-glow)]">
                        <x-icon :name="$icon" />
                    </div>
                    <h1 class="mt-6 font-display text-4xl font-light tracking-tight">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="mt-3 text-sm leading-relaxed text-zinc-400">{{ $subtitle }}</p>
                    @endif

                    @if (session('status'))
                        <div class="mt-6 flex items-center gap-2 rounded-xl bg-emerald-950/50 px-3.5 py-2.5 text-sm text-emerald-200 ring-1 ring-emerald-900">
                            <x-icon name="check-circle" class="size-4" />{{ session('status') }}
                        </div>
                    @endif

                    <div class="mt-8">{{ $slot }}</div>
                </div>
                <p class="mt-6 flex items-center justify-center gap-1.5 text-xs text-zinc-500"><x-icon name="lock" class="size-3.5" />Acceso seguro sin contraseña</p>
            </div>
        </div>
    </section>
</x-app-layout>
