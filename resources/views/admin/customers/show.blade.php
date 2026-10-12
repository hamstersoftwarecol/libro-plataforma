<x-admin-layout :title="$customer->email" :heading="$customer->email" :back="route('admin.customers.index')"
                :description="'Cliente desde '.$customer->created_at->translatedFormat('j \d\e F \d\e Y').' · último ingreso '.($customer->last_login_at?->diffForHumans() ?? 'nunca')">
    <x-slot:actions>
        @unless ($customer->is_active)<span class="badge badge-dot bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-900">Bloqueado</span>@endunless
        <form method="POST" action="{{ route('admin.customers.toggle-active', $customer) }}" onsubmit="return confirm('{{ $customer->is_active ? '¿Bloquear a este cliente? Se cerrarán sus sesiones.' : '¿Reactivar a este cliente?' }}')">
            @csrf
            <button class="{{ $customer->is_active ? 'btn btn-danger' : 'btn btn-success' }}"><x-icon :name="$customer->is_active ? 'ban' : 'check'" class="size-4" />{{ $customer->is_active ? 'Bloquear cuenta' : 'Reactivar cuenta' }}</button>
        </form>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Acceso actual</h2>
                @if ($currentSubscription)<span class="{{ $currentSubscription->status->badgeClasses() }}">{{ $currentSubscription->status->label() }}</span>@endif
            </div>
            <div class="card-body">
                @if ($currentSubscription)
                    <p class="text-xl font-semibold">{{ $currentSubscription->plan?->name ?? 'Cortesía' }}</p>
                    <p class="mt-1 text-sm text-zinc-500">Hasta el {{ $currentSubscription->ends_at->translatedFormat('j \d\e F \d\e Y') }} · {{ $currentSubscription->auto_renew ? 'con renovación' : 'sin renovación' }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($currentSubscription->status === \App\Enums\SubscriptionStatus::Active)
                            <form method="POST" action="{{ route('admin.customers.cancel', $customer) }}">@csrf<button class="btn btn-secondary btn-sm">Cancelar renovación</button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.customers.revoke', $customer) }}" onsubmit="return confirm('¿Quitar el acceso de inmediato?')">@csrf<button class="btn btn-danger btn-sm">Revocar acceso ya</button></form>
                    </div>
                @else
                    <p class="text-sm text-zinc-500">Sin acceso activo.</p>
                @endif

                <form method="POST" action="{{ route('admin.customers.extend', $customer) }}" class="mt-6 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                    @csrf
                    <p class="mb-3 flex items-center gap-2 text-sm font-semibold"><x-icon name="sparkles" class="size-4 text-brand-600" />Días de cortesía</p>
                    <div class="flex flex-wrap items-end gap-2">
                        <input type="number" name="days" min="1" max="365" value="7" required class="input input-sm w-20" aria-label="Días">
                        <input name="reason" required maxlength="255" placeholder="Motivo (ej.: compensación)" class="input input-sm min-w-40 flex-1" aria-label="Motivo">
                        <button class="btn btn-primary btn-sm">Extender</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Lectura reciente</h2></div>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($reading as $progress)
                    <li class="flex items-center gap-4 px-6 py-3">
                        <x-book-cover :book="$progress->book" compact class="w-8 shrink-0 rounded" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $progress->book->title }}</p>
                            <div class="bar-track mt-1.5 h-1.5"><div class="bar-fill" style="width: {{ $progress->percentage }}%"></div></div>
                        </div>
                        <span class="text-right text-xs whitespace-nowrap text-zinc-500">{{ number_format($progress->percentage, 0) }}%<br>{{ $progress->updated_at->diffForHumans() }}</span>
                    </li>
                @empty
                    <li><x-empty-state icon="book" title="Todavía no ha leído" class="py-8" /></li>
                @endforelse
            </ul>
        </section>
    </div>

    <section class="mt-6">
        <h2 class="mb-4 text-lg font-semibold">Pagos</h2>
        <div class="table-wrap">
            @if ($payments->isEmpty())
                <x-empty-state icon="card" title="Sin pagos" class="py-8" />
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
                                <td class="font-mono text-xs text-zinc-500">{{ $payment->reference }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    <section class="mt-6">
        <h2 class="mb-4 text-lg font-semibold">Historial de suscripciones</h2>
        <div class="card divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($subscriptions as $subscription)
                <div class="flex flex-wrap items-center justify-between gap-2 px-6 py-3 text-sm">
                    <span class="flex items-center gap-2 font-medium">{{ $subscription->plan?->name ?? 'Cortesía' }} <span class="{{ $subscription->status->badgeClasses() }}">{{ $subscription->status->label() }}</span></span>
                    <span class="text-zinc-500 tabular-nums">{{ $subscription->starts_at->format('d/m/Y') }} → {{ $subscription->ends_at->format('d/m/Y') }}</span>
                </div>
            @empty
                <x-empty-state icon="sparkles" title="Nunca se ha suscrito" class="py-8" />
            @endforelse
        </div>
    </section>
</x-admin-layout>
