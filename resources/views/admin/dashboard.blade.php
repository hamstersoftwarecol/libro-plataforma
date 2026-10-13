@php
    $fmt = fn (int $amount) => \App\Services\Payments\Money::format($amount, $currency ?? 'USD');
    $catalogIcons = ['Libros publicados' => 'book', 'Borradores' => 'pen', 'Categorías' => 'folder', 'Autores' => 'users'];
@endphp

<x-admin-layout title="Resumen" :heading="'Hola, '.auth()->user()->displayName()" :description="now()->translatedFormat('l j \d\e F \d\e Y')">
    <x-slot:actions>
        <a href="{{ route('admin.books.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Nuevo libro</a>
    </x-slot:actions>

    @isset($monthTotals)
        <div class="grid gap-4 md:grid-cols-3">
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 to-brand-800 p-6 text-white shadow-[var(--shadow-glow)]">
                <div class="absolute -top-10 -right-10 size-40 rounded-full bg-white/10 blur-2xl"></div>
                <p class="flex items-center gap-2 text-sm font-medium text-white/80"><x-icon name="trend" class="size-4" />Ingresos del mes</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ $fmt($monthTotals['income']) }}</p>
                <p class="mt-1 text-xs text-white/70">{{ $currency }} · pagos confirmados</p>
            </div>
            <div class="stat">
                <p class="stat-label flex items-center gap-2"><x-icon name="receipt" class="size-4" />Gastos del mes</p>
                <p class="stat-value text-red-600 dark:text-red-400">{{ $fmt($monthTotals['expenses']) }}</p>
            </div>
            <div class="stat">
                <p class="stat-label flex items-center gap-2"><x-icon name="banknotes" class="size-4" />Resultado neto</p>
                <p @class(['stat-value', 'text-red-600 dark:text-red-400' => $monthTotals['net'] < 0, 'text-emerald-600 dark:text-emerald-400' => $monthTotals['net'] >= 0])>{{ $fmt($monthTotals['net']) }}</p>
            </div>
        </div>
        @if ($otherCurrencies->isNotEmpty())
            <p class="mt-3 text-xs text-zinc-500">
                También hay movimientos en
                @foreach ($otherCurrencies as $otherCurrency => $totals){{ $otherCurrency }} (neto {{ \App\Services\Payments\Money::format($totals['net'], $otherCurrency) }})@if (! $loop->last), @endif @endforeach
                · <a href="{{ route('admin.finances.index') }}" class="link">Ver finanzas</a>
            </p>
        @endif

        <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
            @foreach ([
                ['Libros vendidos este mes', $sales['month'], 'sparkles'],
                ['Ventas totales', $sales['total'], 'banknotes'],
                ['Compradores', $sales['buyers'], 'users'],
                ['Accesos regalados', $sales['granted'], 'book'],
                ['Pagos en curso (24 h)', $sales['pending'], 'clock'],
            ] as [$label, $value, $icon])
                <div class="stat">
                    <div class="flex items-center justify-between"><p class="stat-label">{{ $label }}</p><x-icon :name="$icon" class="size-4 text-zinc-400" /></div>
                    <p class="stat-value text-2xl">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        @php $max = max(1, collect($series)->max(fn ($month) => max($month['income'], $month['expenses']))); @endphp
        <div class="card mt-6">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Ingresos y gastos</h2>
                    <p class="text-xs text-zinc-500">Últimos 12 meses · {{ $currency }} · {{ $activeReaders }} lectores activos en 30 días</p>
                </div>
                <div class="flex gap-4 text-xs font-medium text-zinc-500">
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-brand-500"></span>Ingresos</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>Gastos</span>
                </div>
            </div>
            <div class="card-body">
                <div class="flex h-56 items-end gap-2 sm:gap-4">
                    @foreach ($series as $month)
                        <div class="group flex h-full flex-1 flex-col justify-end" title="{{ $month['month']->translatedFormat('F Y') }}: ingresos {{ $fmt($month['income']) }}, gastos {{ $fmt($month['expenses']) }}">
                            <div class="flex h-full items-end justify-center gap-1">
                                <div class="w-1/2 max-w-5 rounded-t-md bg-gradient-to-t from-brand-600 to-brand-400 transition group-hover:from-brand-500" style="height: {{ max(1, $month['income'] / $max * 100) }}%"></div>
                                <div class="w-1/2 max-w-5 rounded-t-md bg-zinc-200 transition group-hover:bg-zinc-300 dark:bg-zinc-700" style="height: {{ max(1, $month['expenses'] / $max * 100) }}%"></div>
                            </div>
                            <p class="mt-3 text-center text-[11px] font-medium text-zinc-500 capitalize">{{ $month['month']->translatedFormat('M') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endisset

    <h2 class="mt-10 mb-4 text-sm font-semibold text-zinc-500">Catálogo</h2>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ($catalog as $label => $value)
            <div class="stat flex items-center gap-4">
                <div class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-300"><x-icon :name="$catalogIcons[$label] ?? 'book'" /></div>
                <div><p class="stat-label">{{ $label }}</p><p class="text-2xl font-semibold tabular-nums">{{ $value }}</p></div>
            </div>
        @endforeach
    </div>
</x-admin-layout>
