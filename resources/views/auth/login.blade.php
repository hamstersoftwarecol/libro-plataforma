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
        </div>
        <button type="submit" class="btn btn-primary w-full py-3">Enviarme el código <x-icon name="arrow-right" class="size-4" /></button>
    </form>

    <p class="mt-6 border-t border-white/[0.06] pt-6 text-center text-sm text-zinc-400">
        ¿Todavía no tienes el libro? <a href="{{ route('checkout.create') }}" class="link">Cómpralo aquí</a>
    </p>
</x-auth-card>
