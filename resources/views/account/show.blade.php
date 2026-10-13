<x-app-layout title="Mi cuenta">
    <div class="flex items-center gap-4">
        <x-avatar :user="auth()->user()" size="size-14" class="text-lg" />
        <div>
            <h1 class="page-title">Mi cuenta</h1>
            <p class="text-zinc-500">{{ auth()->user()->email }}</p>
        </div>
    </div>

    <section class="mt-10">
        <h2 class="text-lg font-semibold">Mis libros</h2>
        @if ($books->isEmpty() && ! $subscription)
            <div class="card mt-4">
                <x-empty-state icon="book" title="Todavía no tienes libros" description="Compra un libro una vez y léelo para siempre desde tu navegador.">
                    <a href="{{ route('checkout.create') }}" class="btn btn-primary">Comprar «El fondo no era el final»</a>
                </x-empty-state>
            </div>
        @else
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($books as $book)
                    <a href="{{ route('reader.show', $book) }}" class="card group flex items-center gap-4 p-4 transition hover:-translate-y-0.5 hover:shadow-lift">
                        @if ($book->cover_path)
                            <img src="{{ $book->coverUrl() }}" alt="" class="w-14 shrink-0 rounded-md shadow-lg">
                        @else
                            <x-book-cover :book="$book" compact class="w-14 shrink-0 rounded-md" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold group-hover:text-brand-400">{{ $book->title }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $book->authorNames() }}</p>
                            <p class="mt-2 text-xs font-medium text-emerald-400">Acceso de por vida · desde {{ \Illuminate\Support\Carbon::parse($book->pivot->granted_at)->translatedFormat('j M Y') }}</p>
                        </div>
                        <x-icon name="arrow-right" class="size-4 text-zinc-500 transition group-hover:translate-x-1 group-hover:text-brand-400" />
                    </a>
                @endforeach
            </div>
            @if ($subscription)
                <p class="mt-4 text-sm text-zinc-500">También tienes una suscripción anterior activa hasta el {{ $subscription->ends_at->translatedFormat('j \d\e F \d\e Y') }}.</p>
            @endif
        @endif
    </section>

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
                                <td class="font-medium">{{ $payment->description() }}</td>
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
