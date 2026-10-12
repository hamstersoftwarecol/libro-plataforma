@props(['user', 'size' => 'size-8'])

@php
    $initials = collect(preg_split('/[\s._-]+/', $user->name ?: strstr($user->email, '@', true)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
    $hue = crc32($user->email) % 360;
@endphp

<span {{ $attributes->class("$size inline-grid shrink-0 place-items-center rounded-full text-xs font-semibold text-white ring-2 ring-white dark:ring-zinc-900") }}
      style="background: linear-gradient(135deg, oklch(0.62 0.17 {{ $hue }}), oklch(0.5 0.2 {{ ($hue + 40) % 360 }}))">{{ $initials ?: '?' }}</span>
