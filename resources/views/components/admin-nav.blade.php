@props(['sections'])

<nav class="flex flex-1 flex-col gap-6 overflow-y-auto px-3 py-4">
    @foreach ($sections as $sectionName => $items)
        <div>
            <p class="px-3 pb-2 text-[11px] font-semibold tracking-[0.14em] text-zinc-400 uppercase">{{ $sectionName }}</p>
            <ul class="space-y-0.5">
                @foreach ($items as [$routeName, $label, $icon, $match])
                    @php $active = request()->routeIs($match); @endphp
                    <li>
                        <a href="{{ route($routeName) }}" @class([
                            'group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition',
                            'bg-brand-50 text-brand-700 dark:bg-brand-950/60 dark:text-brand-300' => $active,
                            'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' => ! $active,
                        ])>
                            <x-icon :name="$icon" :class="$active ? 'size-5 text-brand-600 dark:text-brand-400' : 'size-5 text-zinc-400 group-hover:text-zinc-600 dark:group-hover:text-zinc-300'" />
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
