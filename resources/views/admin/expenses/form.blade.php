<x-admin-layout :title="$expense->exists ? 'Editar gasto' : 'Registrar gasto'" :heading="$expense->exists ? 'Editar gasto' : 'Registrar gasto'" :back="route('admin.expenses.index')"
                :description="'Responsable: '.(($expense->exists ? $expense->creator : auth()->user())?->displayName() ?? '—')">
    <form method="POST" enctype="multipart/form-data" action="{{ $expense->exists ? route('admin.expenses.update', $expense) : route('admin.expenses.store') }}" class="card max-w-3xl">
        @csrf
        @if ($expense->exists) @method('PUT') @endif

        <div class="card-body space-y-5">
            <div>
                <label for="description" class="label">Descripción *</label>
                <input id="description" name="description" required maxlength="255" value="{{ old('description', $expense->description) }}" class="input" placeholder="Ej.: Servidor de octubre">
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="category" class="label">Categoría *</label>
                    <select id="category" name="category" required class="input">
                        @foreach ($categories as $category)
                            <option value="{{ $category->value }}" @selected(old('category', $expense->category?->value) === $category->value)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="spent_on" class="label">Fecha *</label>
                    <input id="spent_on" name="spent_on" type="date" required max="{{ today()->toDateString() }}" value="{{ old('spent_on', $expense->spent_on?->toDateString()) }}" class="input">
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-[1fr_120px]">
                <div>
                    <label for="amount" class="label">Monto *</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" required value="{{ old('amount', $expense->exists ? \App\Services\Payments\Money::toMajor($expense->amount, $expense->currency) : '') }}" class="input text-lg font-semibold tabular-nums">
                </div>
                <div>
                    <label for="currency" class="label">Moneda *</label>
                    <input id="currency" name="currency" maxlength="3" required value="{{ old('currency', $expense->currency) }}" class="input text-lg uppercase">
                </div>
            </div>
            <div>
                <label for="supplier" class="label">Proveedor</label>
                <input id="supplier" name="supplier" maxlength="255" value="{{ old('supplier', $expense->supplier) }}" class="input">
            </div>
            <div>
                <label for="notes" class="label">Notas</label>
                <textarea id="notes" name="notes" rows="3" class="input">{{ old('notes', $expense->notes) }}</textarea>
            </div>
            <div class="rounded-xl border-2 border-dashed border-zinc-200 p-5 dark:border-zinc-700">
                <label for="receipt" class="label flex items-center gap-2"><x-icon name="upload" class="size-4 text-brand-600" />Comprobante (factura o recibo)</label>
                @if ($expense->receipt_path)
                    <a href="{{ route('admin.expenses.receipt', $expense) }}" class="link mb-3 inline-flex items-center gap-1.5 text-sm"><x-icon name="download" class="size-4" />{{ $expense->receipt_name }}</a>
                @endif
                <input id="receipt" name="receipt" type="file" accept=".pdf,image/jpeg,image/png,image/webp" class="input-file">
                <p class="hint">PDF o imagen · máx. 10 MB · almacenamiento privado</p>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
            @if ($expense->exists)
                <button form="delete-expense" class="btn btn-ghost text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"><x-icon name="trash" class="size-4" />Eliminar</button>
            @else
                <span></span>
            @endif
            <button class="btn btn-primary">{{ $expense->exists ? 'Guardar cambios' : 'Registrar gasto' }}</button>
        </div>
    </form>

    @if ($expense->exists)
        <form id="delete-expense" method="POST" action="{{ route('admin.expenses.destroy', $expense) }}" onsubmit="return confirm('¿Eliminar este gasto?')">@csrf @method('DELETE')</form>
    @endif
</x-admin-layout>
