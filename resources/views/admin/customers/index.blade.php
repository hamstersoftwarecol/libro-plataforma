<x-admin-layout title="Clientes" heading="Clientes" :description="$customers->total().' lectores registrados'">
    <form method="GET" class="mb-6 flex flex-wrap gap-2">
        <div class="relative min-w-60 flex-1 sm:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Correo o nombre" class="input pl-10">
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach (['' => 'Todos', 'subscribed' => 'Con acceso', 'past_due' => 'Pago pendiente', 'without' => 'Sin suscripción', 'inactive' => 'Bloqueados'] as $value => $label)
                <a href="{{ route('admin.customers.index', array_filter(['q' => $filters['q'] ?? null, 'status' => $value])) }}" @class(['chip', 'chip-active' => ($filters['status'] ?? '') === $value])>{{ $label }}</a>
            @endforeach
        </div>
    </form>

    <div class="table-wrap">
        @if ($customers->isEmpty())
            <x-empty-state icon="users" title="No hay clientes con esos filtros" />
        @else
            <table class="table">
                <thead><tr><th>Cliente</th><th>Suscripción</th><th>Vence</th><th class="text-right">Pagos</th><th>Último ingreso</th><th></th></tr></thead>
                <tbody>
                    @foreach ($customers as $customer)
                        @php $subscription = $customer->subscriptions->first(); @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.customers.show', $customer) }}" class="flex items-center gap-3">
                                    <x-avatar :user="$customer" />
                                    <div class="min-w-0">
                                        <p class="truncate font-medium hover:text-brand-600">{{ $customer->email }}</p>
                                        @if (! $customer->is_active)<span class="text-xs font-semibold text-red-600">Bloqueado</span>@endif
                                    </div>
                                </a>
                            </td>
                            <td>
                                @if ($subscription)
                                    <div class="flex items-center gap-2"><span class="text-zinc-600 dark:text-zinc-300">{{ $subscription->plan?->name ?? 'Cortesía' }}</span><span class="{{ $subscription->status->badgeClasses() }}">{{ $subscription->status->label() }}</span></div>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-zinc-500">{{ $subscription?->ends_at->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ $customer->paid_payments_count }}</td>
                            <td class="whitespace-nowrap text-zinc-500">{{ $customer->last_login_at?->diffForHumans() ?? 'Nunca' }}</td>
                            <td class="text-right"><a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-ghost btn-sm"><x-icon name="chevron-right" class="size-4" /></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="mt-6">{{ $customers->links() }}</div>
</x-admin-layout>
