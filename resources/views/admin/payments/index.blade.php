<x-admin-layout title="Pagos" heading="Pagos" description="Todos los cobros recibidos por la pasarela.">
    <form method="GET" class="mb-6 flex flex-wrap gap-2">
        <div class="relative min-w-60 flex-1 sm:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Correo o referencia" class="input pl-10">
        </div>
        <select name="status" class="input w-auto" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary"><x-icon name="filter" class="size-4" />Filtrar</button>
    </form>

    <div class="table-wrap">
        @if ($payments->isEmpty())
            <x-empty-state icon="card" title="No hay pagos" />
        @else
            <table class="table">
                <thead><tr><th>Fecha</th><th>Cliente</th><th>Plan</th><th class="text-right">Monto</th><th>Estado</th><th>Pasarela</th></tr></thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="whitespace-nowrap text-zinc-500">{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($payment->user)
                                    <a href="{{ route('admin.customers.show', $payment->user) }}" class="flex items-center gap-2 font-medium hover:text-brand-600"><x-avatar :user="$payment->user" size="size-7" />{{ $payment->user->email }}</a>
                                @else
                                    <span class="flex items-center gap-2 text-zinc-400"><span class="grid size-7 place-items-center rounded-full bg-white/5 text-xs">?</span>{{ $payment->email }} <span class="text-xs">(sin cuenta aún)</span></span>
                                @endif
                            </td>
                            <td>{{ $payment->plan->name }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ $payment->formattedAmount() }}</td>
                            <td>
                                <span class="{{ $payment->status->badgeClasses() }}">{{ $payment->status->label() }}</span>
                                @if ($payment->failure_reason)<p class="mt-1 text-xs text-zinc-500">{{ $payment->failure_reason }}</p>@endif
                            </td>
                            <td class="text-xs text-zinc-500"><span class="font-medium capitalize">{{ $payment->gateway }}</span><br><span class="font-mono">{{ $payment->gateway_payment_id }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="mt-6">{{ $payments->links() }}</div>
</x-admin-layout>
