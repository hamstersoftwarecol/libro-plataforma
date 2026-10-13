<x-auth-card title="Ingresa a tu biblioteca" subtitle="Escribe el correo con el que compraste el libro y te enviaremos un código de acceso.">
    <form method="POST" action="{{ route('login.send') }}" class="space-y-5">
        @csrf
        <div>
            <label for="email" class="label">Correo electrónico</label>
            <div class="relative">
                <x-icon name="mail" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-zinc-400" />
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="tu@correo.com" class="input py-3 pl-10">
            </div>
            @error('email')<p class="error">{{ $message }}</p>@enderror
            @if (session('needs_purchase'))
                <a href="{{ route('checkout.create') }}" class="mt-3 flex items-center justify-between gap-3 rounded-xl bg-brand-500/10 px-4 py-3 text-sm text-brand-200 ring-1 ring-brand-500/40 transition hover:bg-brand-500/20">
                    <span>¿Aún no tienes el libro? <strong class="text-white">Cómpralo y empieza a leer</strong></span>
                    <x-icon name="arrow-right" class="size-4" />
                </a>
            @endif
        </div>
        <button type="submit" class="btn btn-primary w-full py-3">Enviarme el código <x-icon name="arrow-right" class="size-4" /></button>
    </form>

    <p class="mt-6 border-t border-white/[0.06] pt-6 text-center text-sm text-zinc-400">
        ¿Todavía no tienes el libro? <a href="{{ route('checkout.create') }}" class="link">Cómpralo aquí</a>
    </p>
</x-auth-card>
