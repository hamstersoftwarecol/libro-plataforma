<x-admin-layout title="Gastos" heading="Gastos" description="Costos operativos con su comprobante.">
    <x-slot:actions>
        <a href="{{ route('admin.expenses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Registrar gasto</a>
    </x-slot:actions>

    <form method="GET" class="card card-body mb-6 flex flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1">
            <label class="label">Categoría</label>
            <select name="category" class="input">
                <option value="">Todas</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}" @selected(($filters['category'] ?? null) === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Desde</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
        <div><label class="label">Hasta</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
        <button class="btn btn-secondary"><x-icon name="filter" class="size-4" />Filtrar</button>
        @if ($totals->isNotEmpty())
            <div class="ml-auto text-right">
                <p class="text-xs font-medium text-zinc-500">Total filtrado</p>
                @foreach ($totals as $currency => $total)
                    <p class="text-xl font-semibold tabular-nums">{{ \App\Services\Payments\Money::format((int) $total, $currency) }}</p>
                @endforeach
            </div>
        @endif
    </form>

    <div class="table-wrap">
        @if ($expenses->isEmpty())
            <x-empty-state icon="receipt" title="No hay gastos registrados" description="Registra servidores, regalías, marketing y demás costos.">
                <a href="{{ route('admin.expenses.create') }}" class="btn btn-primary">Registrar gasto</a>
            </x-empty-state>
        @else
            <table class="table">
                <thead><tr><th>Fecha</th><th>Descripción</th><th>Categoría</th><th>Responsable</th><th class="text-right">Monto</th><th></th></tr></thead>
                <tbody>
                    @foreach ($expenses as $expense)
                        <tr>
                            <td class="whitespace-nowrap text-zinc-500">{{ $expense->spent_on->format('d/m/Y') }}</td>
                            <td>
                                <p class="font-medium">{{ $expense->description }}</p>
                                @if ($expense->supplier)<p class="text-xs text-zinc-500">{{ $expense->supplier }}</p>@endif
                            </td>
                            <td><span class="badge bg-zinc-50 text-zinc-600 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700">{{ $expense->category->label() }}</span></td>
                            <td class="text-zinc-500">{{ $expense->creator?->displayName() ?? '—' }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ $expense->formattedAmount() }}</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($expense->receipt_path)
                                    <a href="{{ route('admin.expenses.receipt', $expense) }}" class="btn btn-ghost btn-sm" title="Descargar comprobante"><x-icon name="download" class="size-4" /></a>
                                @endif
                                <a href="{{ route('admin.expenses.edit', $expense) }}" class="btn btn-ghost btn-sm">Editar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="mt-6">{{ $expenses->links() }}</div>
</x-admin-layout>
