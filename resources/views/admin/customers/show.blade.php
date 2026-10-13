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
            <div class="card-header"><h2 class="card-title">Libros con acceso de por vida</h2><span class="text-xs text-zinc-500">{{ $books->count() }}</span></div>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($books as $book)
                    <li class="flex items-center gap-4 px-6 py-3">
                        <x-book-cover :book="$book" compact class="w-8 shrink-0 rounded" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $book->title }}</p>
                            <p class="text-xs text-zinc-500">{{ $book->pivot->payment_id ? 'Comprado' : 'Otorgado' }} el {{ \Illuminate\Support\Carbon::parse($book->pivot->granted_at)->format('d/m/Y') }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.customers.books.revoke', [$customer, $book]) }}" onsubmit="return confirm('¿Quitar el acceso a este libro?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost btn-sm text-zinc-400 hover:text-red-500" title="Quitar acceso"><x-icon name="trash" class="size-4" /></button>
                        </form>
                    </li>
                @empty
                    <li><x-empty-state icon="book" title="No tiene libros" class="py-8" /></li>
                @endforelse
            </ul>
            @if ($availableBooks->isNotEmpty())
                <form method="POST" action="{{ route('admin.customers.books.grant', $customer) }}" class="flex flex-wrap items-end gap-2 border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                    @csrf
                    <label class="min-w-48 flex-1 text-xs text-zinc-500">Dar acceso sin pago (regalo o compensación)
                        <select name="book_id" class="input input-sm mt-1">
                            @foreach ($availableBooks as $availableBook)
                                <option value="{{ $availableBook->id }}">{{ $availableBook->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn btn-primary btn-sm">Dar acceso</button>
                </form>
            @endif
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
                                <td class="font-medium">{{ $payment->description() }}</td>
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

</x-admin-layout>
