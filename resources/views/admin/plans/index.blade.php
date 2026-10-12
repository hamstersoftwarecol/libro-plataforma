<x-admin-layout title="Planes" heading="Planes" description="Cambiar un precio solo afecta a los pagos nuevos.">
    <div class="space-y-4">
        @foreach ($plans->push(new \App\Models\Plan(['interval' => 'monthly', 'currency' => config('payments.currency'), 'is_active' => true])) as $plan)
            <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}"
                  @class(['card card-body', 'border-2 border-dashed border-zinc-200 bg-transparent shadow-none ring-0 dark:border-zinc-800' => ! $plan->exists])>
                @csrf
                @if ($plan->exists) @method('PUT') @endif

                <div class="mb-5 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-300"><x-icon :name="$plan->exists ? 'sparkles' : 'plus'" /></div>
                        <div>
                            <p class="font-semibold">{{ $plan->exists ? $plan->name : 'Nuevo plan' }}</p>
                            @if ($plan->exists)<p class="text-xs text-zinc-500">{{ $plan->subscriptions_count }} {{ $plan->subscriptions_count === 1 ? 'suscriptor activo' : 'suscriptores activos' }} · {{ $plan->formattedPrice() }} {{ $plan->interval->perLabel() }}</p>@endif
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="checkbox" @checked($plan->is_active)> Activo
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_90px_80px]">
                    <div><label class="label">Nombre</label><input name="name" value="{{ $plan->name }}" required class="input"></div>
                    <div>
                        <label class="label">Periodo</label>
                        <select name="interval" class="input">
                            @foreach ($intervals as $interval)
                                <option value="{{ $interval->value }}" @selected($plan->interval === $interval)>{{ $interval->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label class="label">Precio</label><input name="price" type="number" step="0.01" min="0.01" required value="{{ $plan->exists ? \App\Services\Payments\Money::toMajor($plan->price, $plan->currency) : '' }}" class="input tabular-nums"></div>
                    <div><label class="label">Moneda</label><input name="currency" maxlength="3" required value="{{ $plan->currency }}" class="input uppercase"></div>
                    <div><label class="label">Orden</label><input name="sort_order" type="number" min="0" value="{{ $plan->sort_order }}" class="input"></div>
                </div>
                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <div class="min-w-60 flex-1"><label class="label">Descripción</label><input name="description" value="{{ $plan->description }}" class="input"></div>
                    <button class="{{ $plan->exists ? 'btn btn-secondary' : 'btn btn-primary' }}">{{ $plan->exists ? 'Guardar' : 'Crear plan' }}</button>
                </div>
            </form>
        @endforeach
    </div>
</x-admin-layout>
