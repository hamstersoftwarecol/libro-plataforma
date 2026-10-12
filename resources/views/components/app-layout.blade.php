@props(['title' => null, 'bare' => false])

<!DOCTYPE html>
<html lang="es" class="dark h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <x-fonts />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cine-grain flex min-h-full flex-col">
    <header class="sticky top-0 z-40 border-b border-zinc-200/70 bg-white/75 backdrop-blur-xl dark:border-zinc-800/70 dark:bg-zinc-950/75">
        <nav class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="shrink-0"><x-logo /></a>

            @php
                $links = [['route' => 'plans.index', 'label' => 'Planes', 'match' => 'plans.*']];
                if (auth()->check()) {
                    array_unshift($links, ['route' => 'library', 'label' => 'Biblioteca', 'match' => 'library*']);
                }
            @endphp

            <div class="hidden items-center gap-1 md:flex">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}" @class([
                        'rounded-lg px-3 py-2 text-sm font-medium transition',
                        'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' => request()->routeIs($link['match']),
                        'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => ! request()->routeIs($link['match']),
                    ])>{{ $link['label'] }}</a>
                @endforeach
            </div>

            <div class="ml-auto flex items-center gap-2">
                <x-language-switcher />
                @auth
                    @can('access-admin')
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm hidden sm:inline-flex"><x-icon name="chart" class="size-4" />Panel</a>
                    @endcan

                    <details class="group relative">
                        <summary class="flex cursor-pointer items-center gap-2 rounded-full p-0.5 pr-2 transition hover:bg-zinc-100 dark:hover:bg-zinc-800">
                            <x-avatar :user="auth()->user()" />
                            <x-icon name="chevron-down" class="size-4 text-zinc-400 transition group-open:rotate-180" />
                        </summary>
                        <div class="absolute right-0 mt-2 w-64 origin-top-right rounded-2xl bg-white p-2 shadow-lift ring-1 ring-zinc-200 animate-fade-up dark:bg-zinc-900 dark:ring-zinc-800">
                            <div class="px-3 py-2.5">
                                <p class="truncate text-sm font-semibold">{{ auth()->user()->displayName() }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ auth()->user()->email }}</p>
                            </div>
                            <div class="my-1 h-px bg-zinc-100 dark:bg-zinc-800"></div>
                            <a href="{{ route('library') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"><x-icon name="library" class="size-4 text-zinc-400" />Biblioteca</a>
                            <a href="{{ route('account') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"><x-icon name="user" class="size-4 text-zinc-400" />Mi cuenta</a>
                            <a href="{{ route('plans.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm hover:bg-zinc-100 md:hidden dark:hover:bg-zinc-800"><x-icon name="sparkles" class="size-4 text-zinc-400" />Planes</a>
                            @can('access-admin')
                                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"><x-icon name="chart" class="size-4 text-zinc-400" />Administración</a>
                            @endcan
                            <div class="my-1 h-px bg-zinc-100 dark:bg-zinc-800"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"><x-icon name="logout" class="size-4" />Cerrar sesión</button>
                            </form>
                        </div>
                    </details>
                @else
                    <a href="{{ route('plans.index') }}" class="btn btn-ghost btn-sm md:hidden">Planes</a>
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Ingresar</a>
                @endauth
            </div>
        </nav>
    </header>

    <main @class(['flex-1', 'mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8' => ! $bare])>
        @unless ($bare)
            <x-alerts />
        @endunless
        {{ $slot }}
    </main>

    <footer class="border-t border-zinc-200/70 dark:border-zinc-800/70">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 text-sm text-zinc-500 sm:flex-row sm:px-6 lg:px-8">
            <x-logo class="opacity-80" />
            <div class="flex items-center gap-6">
                <a href="{{ route('plans.index') }}" class="hover:text-zinc-900 dark:hover:text-white">Planes</a>
                @auth<a href="{{ route('account') }}" class="hover:text-zinc-900 dark:hover:text-white">Mi cuenta</a>@endauth
                <span>© {{ now()->year }} {{ config('app.name') }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
