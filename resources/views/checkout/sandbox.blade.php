<x-auth-card title="Pasarela de prueba" icon="card" subtitle="Simulación local: no se cobra dinero real.">
    <div class="-mt-2 rounded-2xl bg-gradient-to-br from-zinc-900 to-brand-950 p-6 text-white shadow-lift">
        <p class="text-xs tracking-widest text-white/60 uppercase">{{ $payment->description() }}</p>
        <p class="mt-2 font-display text-4xl font-semibold tabular-nums">{{ $payment->formattedAmount() }}</p>
        <p class="mt-6 truncate text-sm text-white/70">{{ $payment->email }}</p>
    </div>

    @if ($payment->isPending())
        <form method="POST" action="{{ URL::temporarySignedRoute('sandbox.complete', now()->addMinutes(30), $payment) }}" class="mt-6 grid gap-3">
            @csrf
            <button name="outcome" value="approve" class="btn btn-success py-3"><x-icon name="check" class="size-4" />Simular pago aprobado</button>
            <button name="outcome" value="decline" class="btn btn-danger py-3"><x-icon name="x" class="size-4" />Simular pago rechazado</button>
        </form>
    @else
        <p class="mt-6 text-sm">Este pago ya fue procesado: <strong>{{ $payment->status->label() }}</strong>.</p>
        <a href="{{ route('checkout.show', $payment) }}" class="btn btn-secondary mt-4 w-full">Ver resultado</a>
    @endif
</x-auth-card>
