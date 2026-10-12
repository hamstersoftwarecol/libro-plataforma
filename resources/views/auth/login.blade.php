<x-auth-card title="Ingresa a tu biblioteca" subtitle="Escribe tu correo y te enviaremos un código de acceso. Si es tu primera vez, creamos tu cuenta automáticamente.">
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
</x-auth-card>
