<x-auth-card title="Revisa tu correo" icon="shield">
    <p class="-mt-4 mb-8 text-sm text-zinc-600 dark:text-zinc-400">
        Enviamos un código de 6 dígitos a <span class="font-semibold text-zinc-900 dark:text-white">{{ $email }}</span>. Vence en {{ \App\Services\LoginCodeService::EXPIRES_IN_MINUTES }} minutos.
    </p>

    <form method="POST" action="{{ route('login.verify.store') }}" class="space-y-5" id="verify-form">
        @csrf
        <div>
            <label for="code" class="label">Código de acceso</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code" placeholder="••••••"
                   class="input py-4 text-center font-mono text-3xl font-semibold tracking-[0.6em] placeholder:tracking-[0.6em]">
            @error('code')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn-primary w-full py-3">Ingresar</button>
    </form>

    <div class="mt-6 flex items-center justify-between border-t border-zinc-100 pt-6 text-sm dark:border-zinc-800">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-zinc-500 hover:text-zinc-900 dark:hover:text-white"><x-icon name="arrow-left" class="size-4" />Otro correo</a>
        <form method="POST" action="{{ route('login.resend') }}">
            @csrf
            <button type="submit" class="link">Reenviar código</button>
        </form>
    </div>
    @error('email')<p class="error">{{ $message }}</p>@enderror

    <script>
        (() => {
            const form = document.getElementById('verify-form');
            const input = document.getElementById('code');
            const button = form.querySelector('button[type="submit"]');
            let submitted = false;

            const submitWhenComplete = () => {
                const digits = input.value.replace(/\D/g, '').slice(0, 6);
                if (input.value !== digits) input.value = digits;

                if (digits.length === 6 && ! submitted) {
                    submitted = true;
                    button.disabled = true;
                    button.textContent = 'Verificando…';
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            };

            input.addEventListener('paste', (event) => {
                const pasted = (event.clipboardData || window.clipboardData)?.getData('text') || '';
                const digits = pasted.replace(/\D/g, '').slice(0, 6);
                if (digits) {
                    event.preventDefault();
                    input.value = digits;
                    submitWhenComplete();
                }
            });
            input.addEventListener('input', submitWhenComplete);
            form.addEventListener('submit', () => { submitted = true; });
        })();
    </script>
</x-auth-card>
