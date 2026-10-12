<x-admin-layout title="Configuración" heading="Configuración" description="Correo saliente y pasarela de pagos. Las claves se guardan cifradas y nunca se muestran.">
    @if ($errors->has('mail') || $errors->has('bold'))
        <div class="mb-6 flex items-start gap-3 rounded-2xl bg-red-950/50 px-4 py-3.5 text-sm text-red-200 ring-1 ring-red-900">
            <x-icon name="warning" class="size-5 text-red-400" />
            <p>{{ $errors->first('mail') ?: $errors->first('bold') }}</p>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        {{-- SMTP --}}
        <section class="card">
            <div class="card-header">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 place-items-center rounded-xl bg-brand-500/15 text-brand-400"><x-icon name="mail" /></div>
                    <div>
                        <h2 class="card-title">Correo SMTP</h2>
                        <p class="text-xs text-zinc-500">Códigos de acceso, recibos y avisos.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.settings.mail.test') }}">
                    @csrf
                    <button class="btn btn-secondary btn-sm"><x-icon name="mail" class="size-4" />Enviar prueba</button>
                </form>
            </div>

            <form method="POST" action="{{ route('admin.settings.mail') }}" class="card-body space-y-5" autocomplete="off">
                @csrf @method('PUT')
                <div class="grid gap-5 sm:grid-cols-[1fr_110px]">
                    <div>
                        <label for="host" class="label">Servidor SMTP</label>
                        <input id="host" name="host" value="{{ old('host', $mail['host']) }}" required class="input font-mono" placeholder="mail.tudominio.com">
                    </div>
                    <div>
                        <label for="port" class="label">Puerto</label>
                        <input id="port" name="port" type="number" value="{{ old('port', $mail['port']) }}" required class="input font-mono">
                    </div>
                </div>
                <div>
                    <label class="label">Cifrado</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => 'Ninguno'] as $value => $label)
                            <label class="cursor-pointer rounded-xl px-3 py-2.5 text-center text-sm ring-1 ring-white/10 transition hover:ring-brand-500/60 has-[:checked]:bg-brand-500/15 has-[:checked]:text-brand-300 has-[:checked]:ring-brand-500">
                                <input type="radio" name="encryption" value="{{ $value }}" class="sr-only" @checked(old('encryption', $mail['encryption']) === $value)>{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="username" class="label">Usuario</label>
                        <input id="username" name="username" value="{{ old('username', $mail['username']) }}" class="input" autocomplete="off">
                    </div>
                    <div>
                        <label for="password" class="label">Contraseña</label>
                        <input id="password" name="password" type="password" class="input" autocomplete="new-password"
                               placeholder="{{ $mail['has_password'] ? '•••••••• guardada' : 'Sin configurar' }}">
                        <p class="hint">{{ $mail['has_password'] ? 'Déjala vacía para conservar la actual.' : 'Escribe la contraseña del buzón.' }}</p>
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="from_address" class="label">Remitente (correo)</label>
                        <input id="from_address" name="from_address" type="email" value="{{ old('from_address', $mail['from_address']) }}" required class="input">
                    </div>
                    <div>
                        <label for="from_name" class="label">Remitente (nombre)</label>
                        <input id="from_name" name="from_name" value="{{ old('from_name', $mail['from_name']) }}" required class="input">
                    </div>
                </div>
                @foreach (['host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'] as $field)
                    @error($field)<p class="error">{{ $message }}</p>@enderror
                @endforeach
                <div class="flex justify-end border-t border-white/[0.06] pt-5">
                    <button class="btn btn-primary">Guardar correo</button>
                </div>
            </form>
        </section>

        {{-- Bold --}}
        <section class="card">
            <div class="card-header">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 place-items-center rounded-xl bg-white text-sm font-black tracking-tight text-[#121e6c]">bold</div>
                    <div>
                        <h2 class="card-title">Pagos con Bold</h2>
                        <p class="text-xs text-zinc-500">Botón de pagos · tarjetas, PSE, Nequi y más.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.settings.payments.test') }}">
                    @csrf
                    <button class="btn btn-secondary btn-sm"><x-icon name="bolt" class="size-4" />Probar conexión</button>
                </form>
            </div>

            <form method="POST" action="{{ route('admin.settings.payments') }}" class="card-body space-y-5" autocomplete="off">
                @csrf @method('PUT')

                <div>
                    <label class="label">Pasarela activa</label>
                    <div class="grid grid-cols-2 gap-2">
                        @if ($payments['sandbox_available'])
                            <label class="cursor-pointer rounded-xl p-3 ring-1 ring-white/10 transition hover:ring-brand-500/60 has-[:checked]:bg-brand-500/15 has-[:checked]:ring-brand-500">
                                <input type="radio" name="gateway" value="sandbox" class="sr-only" @checked(old('gateway', $payments['gateway']) === 'sandbox')>
                                <p class="text-sm font-semibold">Pruebas internas</p>
                                <p class="text-xs text-zinc-500">Simulación, sin cobros</p>
                            </label>
                        @endif
                        <label class="cursor-pointer rounded-xl p-3 ring-1 ring-white/10 transition hover:ring-brand-500/60 has-[:checked]:bg-brand-500/15 has-[:checked]:ring-brand-500">
                            <input type="radio" name="gateway" value="bold" class="sr-only" @checked(old('gateway', $payments['gateway']) === 'bold')>
                            <p class="text-sm font-semibold">Bold</p>
                            <p class="text-xs text-zinc-500">Cobros con Bold</p>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="label">Ambiente de Bold</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['test' => 'Pruebas', 'production' => 'Producción'] as $value => $label)
                            <label class="cursor-pointer rounded-xl px-3 py-2.5 text-center text-sm ring-1 ring-white/10 transition hover:ring-brand-500/60 has-[:checked]:bg-brand-500/15 has-[:checked]:text-brand-300 has-[:checked]:ring-brand-500">
                                <input type="radio" name="bold_environment" value="{{ $value }}" class="sr-only" @checked(old('bold_environment', $payments['bold_environment']) === $value)>{{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="hint">Usa las llaves del mismo ambiente que elijas (Panel de Bold → Integraciones → Llaves).</p>
                </div>

                <div>
                    <label for="bold_identity_key" class="label">Llave de identidad</label>
                    <input id="bold_identity_key" name="bold_identity_key" value="{{ old('bold_identity_key', $payments['bold_identity_key']) }}" class="input font-mono text-xs" autocomplete="off" spellcheck="false">
                    <p class="hint">Es pública: se usa en el botón de pago.</p>
                </div>
                <div>
                    <label for="bold_secret_key" class="label">Llave secreta</label>
                    <input id="bold_secret_key" name="bold_secret_key" type="password" class="input font-mono text-xs" autocomplete="new-password"
                           placeholder="{{ $payments['has_bold_secret'] ? '•••••••• guardada' : 'Sin configurar' }}">
                    <p class="hint">Firma los pagos y verifica los webhooks. {{ $payments['has_bold_secret'] ? 'Déjala vacía para conservar la actual.' : '' }}</p>
                </div>

                @foreach (['gateway', 'bold_environment', 'bold_identity_key', 'bold_secret_key'] as $field)
                    @error($field)<p class="error">{{ $message }}</p>@enderror
                @endforeach

                <div class="space-y-3 rounded-xl bg-white/[0.03] p-4 ring-1 ring-white/[0.06]">
                    <p class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">Registra estas URL en el panel de Bold</p>
                    @foreach (['URL del webhook' => $payments['webhook_url'], 'URL de retorno' => $payments['return_url']] as $label => $url)
                        <div>
                            <p class="text-xs text-zinc-500">{{ $label }}</p>
                            <div class="mt-1 flex items-center gap-2">
                                <code class="min-w-0 flex-1 truncate rounded-lg bg-black/30 px-3 py-2 text-xs text-zinc-200">{{ $url }}</code>
                                <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText('{{ $url }}'); this.textContent = 'Copiada'">Copiar</button>
                            </div>
                        </div>
                    @endforeach
                    <p class="text-xs text-zinc-500">Bold exige HTTPS público. En tu equipo local el webhook no llegará, pero el acceso se activa igual porque al volver de Bold el servidor consulta el pago directamente.</p>
                </div>

                @if ($payments['incompatible_plans']->isNotEmpty())
                    <div class="flex items-start gap-3 rounded-xl bg-amber-500/10 p-4 text-sm text-amber-200 ring-1 ring-amber-500/30">
                        <x-icon name="warning" class="size-5 text-amber-400" />
                        <div>
                            <p class="font-semibold">Planes no compatibles con Bold</p>
                            <p class="mt-1 text-xs leading-relaxed">Bold cobra en COP o USD sin decimales (mínimo $1.000 COP). Ajusta estos planes en <a href="{{ route('admin.plans.index') }}" class="underline">Planes</a>:
                                {{ $payments['incompatible_plans']->map(fn ($plan) => $plan->name.' ('.$plan->formattedPrice().')')->join(', ') }}.</p>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end border-t border-white/[0.06] pt-5">
                    <button class="btn btn-primary">Guardar pagos</button>
                </div>
            </form>
        </section>
    </div>
</x-admin-layout>
