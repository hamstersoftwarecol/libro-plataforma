@php
    use App\Services\Analytics\ReadingAnalytics;
    $maxDay = max(1, collect($series)->max('seconds'));
    $maxWeekday = max(1, max($weekdays));
    $maxCategory = max(1, $categories->max('seconds') ?? 0);
    $weekdayNames = [1 => 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
@endphp

<x-admin-layout title="Analítica de lectura" heading="Analítica de lectura" description="Datos agregados y anónimos.">
    <x-slot:actions>
        @foreach ($ranges as $value => $label)
            <a href="{{ route('admin.analytics.index', ['days' => $value]) }}" @class(['chip', 'chip-active' => $days === $value])>{{ $label }}</a>
        @endforeach
    </x-slot:actions>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        @foreach ([
            ['Lectores activos', $summary['readers'], 'users'],
            ['Tiempo de lectura', ReadingAnalytics::duration($summary['seconds']), 'clock'],
            ['Promedio por lector', ReadingAnalytics::duration($summary['seconds_per_reader']), 'user'],
            ['Páginas leídas', number_format($summary['pages'], 0, ',', '.'), 'book'],
        ] as [$label, $value, $icon])
            <div class="stat">
                <div class="flex items-center justify-between"><p class="stat-label">{{ $label }}</p><x-icon :name="$icon" class="size-4 text-zinc-400" /></div>
                <p class="stat-value text-2xl">{{ $value }}</p>
            </div>
        @endforeach
        <div class="stat ring-amber-200 dark:ring-amber-900/60">
            <div class="flex items-center justify-between"><p class="stat-label">Suscriptores sin leer</p><x-icon name="warning" class="size-4 text-amber-500" /></div>
            <p class="stat-value text-2xl text-amber-600 dark:text-amber-400">{{ $summary['idle_subscribers'] }}</p>
            <p class="text-xs text-zinc-500">riesgo de cancelación</p>
        </div>
    </div>

    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Tiempo de lectura por día</h2></div>
        <div class="card-body">
            <div class="flex h-48 items-end gap-[2px]">
                @foreach ($series as $day)
                    <div class="group flex h-full flex-1 items-end" title="{{ $day['date']->translatedFormat('D j M') }}: {{ ReadingAnalytics::duration($day['seconds']) }}, {{ $day['readers'] }} lectores">
                        <div class="w-full rounded-t-sm bg-gradient-to-t from-brand-600 to-brand-400 opacity-80 transition group-hover:opacity-100" style="height: {{ max(0.5, $day['seconds'] / $maxDay * 100) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex justify-between text-xs font-medium text-zinc-500">
                <span>{{ $series[0]['date']->translatedFormat('j M') }}</span>
                <span>{{ end($series)['date']->translatedFormat('j M') }}</span>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card card-body">
            <h2 class="card-title">Por día de la semana</h2>
            <div class="mt-5 space-y-3">
                @foreach ($weekdays as $weekday => $seconds)
                    <div class="grid grid-cols-[40px_1fr_80px] items-center gap-3 text-sm">
                        <span class="font-medium text-zinc-500">{{ $weekdayNames[$weekday] }}</span>
                        <div class="bar-track"><div class="bar-fill" style="width: {{ $seconds / $maxWeekday * 100 }}%"></div></div>
                        <span class="text-right tabular-nums text-zinc-600 dark:text-zinc-400">{{ ReadingAnalytics::duration($seconds) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card card-body">
            <h2 class="card-title">Por categoría</h2>
            <div class="mt-5 space-y-3">
                @forelse ($categories as $category)
                    <div class="grid grid-cols-[minmax(0,140px)_1fr_90px] items-center gap-3 text-sm">
                        <span class="truncate font-medium text-zinc-600 dark:text-zinc-400">{{ $category['category'] }}</span>
                        <div class="bar-track"><div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400" style="width: {{ $category['seconds'] / $maxCategory * 100 }}%"></div></div>
                        <span class="text-right tabular-nums text-zinc-600 dark:text-zinc-400">{{ ReadingAnalytics::duration($category['seconds']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">Sin actividad en este periodo.</p>
                @endforelse
            </div>
        </div>
    </div>

    <h2 class="mt-10 mb-4 text-lg font-semibold">Libros más leídos</h2>
    <div class="table-wrap">
        @if ($topBooks->isEmpty())
            <x-empty-state icon="trend" title="Todavía no hay lecturas en este periodo" />
        @else
            <table class="table">
                <thead><tr><th>#</th><th>Libro</th><th class="text-right">Lectores</th><th class="text-right">Tiempo</th><th>Avance medio</th><th class="text-right">Terminados</th><th></th></tr></thead>
                <tbody>
                    @foreach ($topBooks as $row)
                        <tr>
                            <td class="font-display text-lg font-semibold text-zinc-300 dark:text-zinc-600">{{ $loop->iteration }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-book-cover :book="$row['book']" compact class="w-9 shrink-0 rounded" />
                                    <div class="min-w-0"><p class="truncate font-semibold">{{ $row['book']->title }}</p><p class="truncate text-xs text-zinc-500">{{ $row['book']->authorNames() }}</p></div>
                                </div>
                            </td>
                            <td class="text-right tabular-nums">{{ $row['readers'] }}</td>
                            <td class="text-right tabular-nums">{{ ReadingAnalytics::duration($row['seconds']) }}</td>
                            <td class="min-w-36"><div class="flex items-center gap-2"><div class="bar-track flex-1"><div class="bar-fill" style="width: {{ $row['average_progress'] }}%"></div></div><span class="w-9 text-right text-xs tabular-nums text-zinc-500">{{ number_format($row['average_progress'], 0) }}%</span></div></td>
                            <td class="text-right tabular-nums">{{ $row['finished'] }}/{{ $row['started'] }}</td>
                            <td class="text-right"><a href="{{ route('admin.analytics.show', [$row['book'], 'days' => $days]) }}" class="btn btn-ghost btn-sm">Detalle</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-admin-layout>
