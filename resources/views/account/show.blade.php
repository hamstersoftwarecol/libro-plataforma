<x-app-layout title="Mi cuenta">
    <div class="flex items-center gap-4">
        <x-avatar :user="auth()->user()" size="size-14" class="text-lg" />
        <div>
            <h1 class="page-title">Mi cuenta</h1>
            <p class="text-zinc-500">{{ auth()->user()->email }}</p>
        </div>
    </div>

    <div class="mt-10 grid gap-6 lg:grid-cols-[1fr_340px]">
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Suscripción</h2>
                @if ($subscription)<span class="{{ $subscription->status->badgeClasses() }}">{{ $subscription->status->label() }}</span>@endif
            </div>
            <div class="card-body">
                @if ($subscription && $subscription->grantsAccess())
                    <div class="flex flex-wrap items-center justify-between gap-6">
                        <div>
                            <p class="text-2xl font-semibold tracking-tight">{{ $subscription->plan?->name ?? 'Acceso de cortesía' }}</p>
                            <p class="mt-1 text-sm text-zinc-500">
                                @if ($subscription->status === \App\Enums\SubscriptionStatus::Cancelled)
                                    No se renovará · acceso hasta el {{ $subscription->ends_at->translatedFormat('j \d\e F \d\e Y') }}
                                @else
                                    Próxima renovación: {{ $subscription->ends_at->translatedFormat('j \d\e F \d\e Y') }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-semibold tabular-nums">{{ (int) now()->diffInDays($subscription->ends_at) }}</p>
                            <p class="text-xs text-zinc-500">días restantes</p>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3 border-t border-zinc-100 pt-6 dark:border-zinc-800">
                        <a href="{{ route('checkout.create') }}" class="btn btn-primary">Renovar o cambiar plan</a>
                        @if ($subscription->status === \App\Enums\SubscriptionStatus::Cancelled)
                            <form method="POST" action="{{ route('account.subscription.resume') }}">@csrf<button class="btn btn-secondary"><x-icon name="refresh" class="size-4" />Reactivar renovación</button></form>
                        @else
                            <form method="POST" action="{{ route('account.subscription.cancel') }}" onsubmit="return confirm('¿Cancelar la renovación? Mantendrás el acceso hasta el final del periodo pagado.')">@csrf<button class="btn btn-ghost text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40">Cancelar renovación</button></form>
                        @endif
                    </div>
                @elseif ($subscription && $subscription->status === \App\Enums\SubscriptionStatus::PastDue)
                    <div class="flex items-start gap-4">
                        <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-400"><x-icon name="warning" /></div>
                        <div>
                            <p class="font-semibold">Tu suscripción venció el {{ $subscription->ends_at->translatedFormat('j \d\e F') }}</p>
                            <p class="mt-1 text-sm text-zinc-500">No recibimos el pago de renovación. Tu progreso de lectura está guardado.</p>
                            <a href="{{ route('checkout.create') }}" class="btn btn-primary mt-4">Pagar y reactivar</a>
                        </div>
                    </div>
                @else
                    <x-empty-state icon="sparkles" title="No tienes una suscripción activa" description="Suscríbete para leer todo el catálogo desde tu navegador." class="py-6">
                        <a href="{{ route('checkout.create') }}" class="btn btn-primary">Pagar y empezar a leer</a>
                    </x-empty-state>
                @endif
            </div>
        </section>

        <aside class="card card-body">
            <h2 class="card-title">Tu acceso</h2>
            <ul class="mt-4 space-y-4 text-sm">
                <li class="flex gap-3"><x-icon name="mail" class="size-5 text-brand-600" /><span><span class="font-medium">Sin contraseña.</span> <span class="text-zinc-500">Entras con un código enviado a tu correo.</span></span></li>
                <li class="flex gap-3"><x-icon name="device" class="size-5 text-brand-600" /><span><span class="font-medium">Una sesión de lectura a la vez.</span> <span class="text-zinc-500">Al abrir un libro en otro dispositivo se cierra el anterior.</span></span></li>
            </ul>
        </aside>
    </div>

    <section class="mt-10">
        <h2 class="text-lg font-semibold">Historial de pagos</h2>
        <div class="table-wrap mt-4">
            @if ($payments->isEmpty())
                <x-empty-state icon="receipt" title="Aún no tienes pagos" />
            @else
                <table class="table">
                    <thead><tr><th>Fecha</th><th>Plan</th><th class="text-right">Monto</th><th>Estado</th><th>Referencia</th></tr></thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="whitespace-nowrap text-zinc-500">{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                                <td class="font-medium">{{ $payment->plan->name }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ $payment->formattedAmount() }}</td>
                                <td><span class="{{ $payment->status->badgeClasses() }}">{{ $payment->status->label() }}</span></td>
                                <td><a href="{{ route('checkout.show', $payment) }}" class="font-mono text-xs text-zinc-500 hover:text-brand-600">{{ Str::limit($payment->reference, 8, '…') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        <div class="mt-4">{{ $payments->links() }}</div>
    </section>
</x-app-layout>
