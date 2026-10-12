@php
    use App\Services\Analytics\ReadingAnalytics;
    $maxDay = max(1, collect($series)->max('seconds'));
    $started = max(1, $dropOff['started']);
@endphp

<x-admin-layout :title="'Analítica · '.$book->title" :heading="$book->title" :description="$book->authorNames()" :back="route('admin.analytics.index', ['days' => $days])">
    <x-slot:actions>
        @foreach ($ranges as $value => $label)
            <a href="{{ route('admin.analytics.show', [$book, 'days' => $value]) }}" @class(['chip', 'chip-active' => $days === $value])>{{ $label }}</a>
        @endforeach
    </x-slot:actions>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="stat"><p class="stat-label">Lectores</p><p class="stat-value">{{ $totals['readers'] }}</p></div>
        <div class="stat"><p class="stat-label">Tiempo de lectura</p><p class="stat-value">{{ ReadingAnalytics::duration($totals['seconds']) }}</p></div>
        <div class="stat"><p class="stat-label">Páginas leídas</p><p class="stat-value">{{ $totals['pages'] }}</p></div>
        <div class="stat"><p class="stat-label">Lo terminaron</p><p class="stat-value">{{ $dropOff['finished'] }} <span class="text-base font-normal text-zinc-500">de {{ $dropOff['started'] }}</span></p></div>
    </div>

    <div class="card mt-6">
        <div class="card-header">
            <div>
                <h2 class="card-title">Hasta dónde llegan los lectores</h2>
                <p class="text-xs text-zinc-500">Histórico. Cada barra muestra cuántos de los que lo empezaron llegaron al menos a ese punto; una caída brusca señala dónde abandonan.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="flex h-56 items-end gap-2 sm:gap-4">
                @foreach ($dropOff['bands'] as $band => $count)
                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                        <span class="text-xs font-semibold tabular-nums">{{ round($count / $started * 100) }}%</span>
                        <div class="w-full rounded-t-lg bg-gradient-to-t from-brand-700 to-brand-400" style="height: {{ max(1, $count / $started * 100) }}%" title="{{ $count }} lectores"></div>
                        <span class="text-[11px] font-medium text-zinc-500 tabular-nums">{{ str_replace('≥ ', '', $band) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Tiempo de lectura por día</h2></div>
        <div class="card-body">
            <div class="flex h-36 items-end gap-[2px]">
                @foreach ($series as $day)
                    <div class="flex h-full flex-1 items-end" title="{{ $day['date']->translatedFormat('D j M') }}: {{ ReadingAnalytics::duration($day['seconds']) }}">
                        <div class="w-full rounded-t-sm bg-gradient-to-t from-brand-600 to-brand-400 opacity-80" style="height: {{ max(0.5, $day['seconds'] / $maxDay * 100) }}%"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-admin-layout>
