@php $money = fn (int $amount, string $currency) => \App\Services\Payments\Money::format($amount, $currency); @endphp

<x-admin-layout title="Finanzas" heading="Finanzas" :description="$report->from->translatedFormat('j M Y').' — '.$report->to->translatedFormat('j M Y')">
    <x-slot:actions>
        <a href="{{ route('admin.finances.export', $filters) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" />Exportar CSV</a>
    </x-slot:actions>

    <form method="GET" class="mb-8 flex flex-wrap items-end gap-2">
        @foreach ($periods as $value => $label)
            @if ($value !== 'custom')
                <a href="{{ route('admin.finances.index', ['period' => $value]) }}" @class(['chip', 'chip-active' => $filters['period'] === $value])>{{ $label }}</a>
            @endif
        @endforeach
        <details class="relative" @if ($filters['period'] === 'custom') open @endif>
            <summary @class(['chip cursor-pointer', 'chip-active' => $filters['period'] === 'custom'])><x-icon name="calendar" class="mr-1.5 size-4" />Personalizado</summary>
            <div class="absolute left-0 z-20 mt-2 flex flex-wrap items-end gap-2 rounded-2xl bg-white p-4 shadow-lift ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <input type="hidden" name="period" value="custom">
                <div><label class="label">Desde</label><input type="date" name="from" value="{{ $filters['from'] }}" class="input input-sm"></div>
                <div><label class="label">Hasta</label><input type="date" name="to" value="{{ $filters['to'] }}" class="input input-sm"></div>
                <button class="btn btn-primary btn-sm">Aplicar</button>
            </div>
        </details>
    </form>

    @forelse ($totals as $currency => $total)
        <div class="mb-10">
            <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold text-zinc-500"><span class="badge bg-zinc-900 text-white ring-zinc-900 dark:bg-white dark:text-zinc-900">{{ $currency }}</span></h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="stat"><p class="stat-label flex items-center gap-2"><x-icon name="trend" class="size-4 text-emerald-500" />Ingresos</p><p class="stat-value text-emerald-600 dark:text-emerald-400">{{ $money($total['income'], $currency) }}</p></div>
                <div class="stat"><p class="stat-label flex items-center gap-2"><x-icon name="receipt" class="size-4 text-red-500" />Gastos</p><p class="stat-value text-red-600 dark:text-red-400">{{ $money($total['expenses'], $currency) }}</p></div>
                <div @class(['rounded-2xl p-5 text-white shadow-lift', 'bg-gradient-to-br from-emerald-600 to-emerald-800' => $total['net'] >= 0, 'bg-gradient-to-br from-red-600 to-red-800' => $total['net'] < 0])>
                    <p class="text-sm font-medium text-white/80">Resultado neto</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums">{{ $money($total['net'], $currency) }}</p>
                    @if ($total['income'] > 0)<p class="mt-1 text-xs text-white/70">Margen {{ round($total['net'] / $total['income'] * 100) }}%</p>@endif
                </div>
            </div>

            @if ($categoryBreakdown[$currency]->isNotEmpty())
                <div class="card card-body mt-4">
                    <h3 class="card-title">Gastos por categoría</h3>
                    <div class="mt-5 space-y-3">
                        @foreach ($categoryBreakdown[$currency] as $category => $amount)
                            <div class="grid grid-cols-[minmax(0,200px)_1fr_auto] items-center gap-4 text-sm">
                                <span class="truncate text-zinc-600 dark:text-zinc-400">{{ $category }}</span>
                                <div class="bar-track"><div class="h-full rounded-full bg-gradient-to-r from-red-500 to-orange-400" style="width: {{ $amount / max(1, $total['expenses']) * 100 }}%"></div></div>
                                <span class="font-semibold tabular-nums">{{ $money($amount, $currency) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @empty
        <div class="card"><x-empty-state icon="banknotes" title="No hay movimientos en este periodo" description="Prueba con un rango de fechas más amplio." /></div>
    @endforelse

    @if ($movements->isNotEmpty())
        <h2 class="mb-4 text-lg font-semibold">Movimientos</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Concepto</th><th>Categoría</th><th>Cliente / proveedor</th><th class="text-right">Monto</th></tr></thead>
                <tbody>
                    @foreach ($movements as $movement)
                        <tr>
                            <td class="whitespace-nowrap text-zinc-500">{{ $movement['date']->format('d/m/Y') }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span @class(['grid size-8 shrink-0 place-items-center rounded-lg', 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400' => $movement['amount'] >= 0, 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-400' => $movement['amount'] < 0])>
                                        <x-icon :name="$movement['amount'] >= 0 ? 'arrow-right' : 'arrow-left'" class="size-4 {{ $movement['amount'] >= 0 ? '-rotate-45' : '-rotate-45' }}" />
                                    </span>
                                    <span class="font-medium">{{ $movement['concept'] }}</span>
                                </div>
                            </td>
                            <td class="text-zinc-500">{{ $movement['category'] }}</td>
                            <td class="text-zinc-500">{{ $movement['counterpart'] ?: '—' }}</td>
                            <td @class(['text-right font-semibold tabular-nums', 'text-emerald-600 dark:text-emerald-400' => $movement['amount'] >= 0, 'text-red-600 dark:text-red-400' => $movement['amount'] < 0])>{{ $money($movement['amount'], $movement['currency']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($movements->count() >= 200)
            <p class="mt-3 text-xs text-zinc-500">Se muestran los 200 más recientes. Exporta el CSV para ver todos.</p>
        @endif
    @endif
</x-admin-layout>
