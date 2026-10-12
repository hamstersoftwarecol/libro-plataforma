@props(['book', 'compact' => false])

@php
    $hue = [28, 42, 58, 18, 205, 160, 350, 70][crc32($book->title) % 8];
@endphp

<div {{ $attributes->class('relative aspect-[2/3] overflow-hidden rounded-xl shadow-lift ring-1 ring-black/5') }}>
    @if ($book->coverUrl())
        <img src="{{ $book->coverUrl() }}" alt="Portada de {{ $book->title }}" class="h-full w-full object-cover" loading="lazy">
    @else
        <div @class(['flex h-full text-white', 'items-center justify-center' => $compact, 'flex-col justify-between p-[9%]' => ! $compact])
             style="background: radial-gradient(110% 80% at 100% 0%, oklch(0.74 0.12 60 / .55), transparent 55%), linear-gradient(165deg, oklch(0.4 0.07 {{ $hue }}), oklch(0.2 0.03 {{ $hue }}));">
            @if ($compact)
                <span class="font-display text-sm font-semibold">{{ mb_substr($book->title, 0, 1) }}</span>
            @else
            <span class="h-px w-8 bg-white/50"></span>
            <div>
                <p class="line-clamp-4 font-display text-[clamp(0.8rem,1.2vw+0.4rem,1.15rem)] leading-tight font-semibold">{{ $book->title }}</p>
                <p class="mt-2 line-clamp-2 text-[0.7rem] font-medium tracking-wide text-white/75 uppercase">{{ $book->authorNames() }}</p>
            </div>
            @endif
        </div>
        <div class="pointer-events-none absolute inset-y-0 left-0 w-3 bg-gradient-to-r from-black/30 to-transparent"></div>
    @endif
</div>
