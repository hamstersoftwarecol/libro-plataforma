<x-auth-card :title="match ($payment->status) { \App\Enums\PaymentStatus::Paid => '¡Pago confirmado!', \App\Enums\PaymentStatus::Failed => 'No pudimos procesar el pago', default => 'Estamos confirmando tu pago' }"
             :icon="match ($payment->status) { \App\Enums\PaymentStatus::Paid => 'check', \App\Enums\PaymentStatus::Failed => 'x', default => 'clock' }">
    @switch($payment->status)
        @case(\App\Enums\PaymentStatus::Paid)
            <p class="-mt-4 text-zinc-600 dark:text-zinc-400">{{ $payment->book ? 'Ya tienes' : 'Activaste' }} <strong class="text-zinc-900 dark:text-white">{{ $payment->description() }}</strong>.</p>
            @auth
                <a href="{{ route('book.start') }}" class="btn btn-primary mt-8 w-full py-3">Empezar a leer <x-icon name="arrow-right" class="size-4" /></a>
            @else
                <p class="mt-4 text-sm text-zinc-400">Tu acceso quedó asociado a <strong class="text-zinc-200">{{ $payment->email }}</strong>. Ingresa con ese correo y recibirás un código.</p>
                <a href="{{ route('login') }}" class="btn btn-primary mt-6 w-full py-3">Ingresar y leer <x-icon name="arrow-right" class="size-4" /></a>
            @endauth
            @break
        @case(\App\Enums\PaymentStatus::Failed)
            <p class="-mt-4 text-zinc-600 dark:text-zinc-400">{{ $payment->failure_reason }}. No se realizó ningún cobro.</p>
            <a href="{{ route('checkout.create', array_filter(['libro' => $payment->book?->slug])) }}" class="btn btn-primary mt-8 w-full py-3">Intentar de nuevo</a>
            @break
        @default
            <p class="-mt-4 text-zinc-600 dark:text-zinc-400">Esto puede tardar unos segundos. La página se actualizará sola.</p>
            <div class="mt-8 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800"><div class="h-full w-1/3 animate-pulse rounded-full bg-brand-500"></div></div>
            <script>setTimeout(() => window.location.reload(), 4000);</script>
    @endswitch

    <dl class="mt-8 space-y-2 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800/50">
        <div class="flex justify-between"><dt class="text-zinc-500">Monto</dt><dd class="font-semibold tabular-nums">{{ $payment->formattedAmount() }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-zinc-500">Referencia</dt><dd class="truncate font-mono text-xs">{{ $payment->reference }}</dd></div>
    </dl>
</x-auth-card>
