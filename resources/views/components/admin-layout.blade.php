@props(['title' => null, 'heading' => null, 'description' => null, 'back' => null, 'actions' => null])

@php
    $sections = [
        'Contenido' => [
            ['admin.dashboard', 'Resumen', 'home', 'admin.dashboard'],
            ['admin.books.index', 'Libros', 'book', 'admin.books.*'],
            ['admin.categories.index', 'Categorías', 'folder', 'admin.categories.*'],
            ['admin.authors.index', 'Autores', 'pen', 'admin.authors.*'],
            ['admin.analytics.index', 'Analítica', 'trend', 'admin.analytics.*'],
        ],
    ];

    if (auth()->user()->can('view-finances')) {
        $sections['Negocio'] = [
            ['admin.customers.index', 'Clientes', 'users', 'admin.customers.*'],
            ['admin.plans.index', 'Planes', 'sparkles', 'admin.plans.*'],
            ['admin.payments.index', 'Pagos', 'card', 'admin.payments.*'],
            ['admin.expenses.index', 'Gastos', 'receipt', 'admin.expenses.*'],
            ['admin.finances.index', 'Finanzas', 'banknotes', 'admin.finances.*'],
        ];
    }

    if (auth()->user()->can('manage-settings')) {
        $sections['Sistema'] = [
            ['admin.settings.edit', 'Configuración', 'cog', 'admin.settings.*'],
        ];
    }
@endphp

<!DOCTYPE html>
<html lang="es" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}Panel · {{ config('app.name') }}</title>
    <x-fonts />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cine-grain h-full">
    @php
        $nav = function () use ($sections) {
            return view('components.admin-nav', ['sections' => $sections])->render();
        };
    @endphp

    <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-zinc-200/70 bg-white lg:flex dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex h-16 items-center px-6"><a href="{{ route('admin.dashboard') }}"><x-logo /></a></div>
        {!! $nav() !!}
    </aside>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-zinc-200/70 bg-white/80 px-4 backdrop-blur-xl sm:px-6 lg:px-8 dark:border-zinc-800/70 dark:bg-zinc-950/80">
            <details class="relative lg:hidden">
                <summary class="btn btn-ghost btn-sm cursor-pointer px-2"><x-icon name="menu" /></summary>
                <div class="fixed inset-x-3 top-16 z-50 max-h-[80vh] overflow-y-auto rounded-2xl bg-white shadow-lift ring-1 ring-zinc-200 animate-fade-up dark:bg-zinc-900 dark:ring-zinc-800">
                    {!! $nav() !!}
                </div>
            </details>
            <a href="{{ route('admin.dashboard') }}" class="lg:hidden"><x-logo /></a>

            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('library') }}" class="btn btn-ghost btn-sm"><x-icon name="external" class="size-4" /><span class="hidden sm:inline">Ver sitio</span></a>
                <details class="group relative">
                    <summary class="flex cursor-pointer items-center gap-2 rounded-full p-0.5 pr-2 transition hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        <x-avatar :user="auth()->user()" />
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm leading-tight font-medium">{{ auth()->user()->displayName() }}</span>
                            <span class="block text-xs leading-tight text-zinc-500">{{ auth()->user()->role->label() }}</span>
                        </span>
                    </summary>
                    <div class="absolute right-0 mt-2 w-56 rounded-2xl bg-white p-2 shadow-lift ring-1 ring-zinc-200 animate-fade-up dark:bg-zinc-900 dark:ring-zinc-800">
                        <a href="{{ route('account') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"><x-icon name="user" class="size-4 text-zinc-400" />Mi cuenta</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"><x-icon name="logout" class="size-4" />Cerrar sesión</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            @if ($heading || $actions)
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div class="min-w-0">
                        @if ($back)
                            <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-white"><x-icon name="arrow-left" class="size-4" />Volver</a>
                        @endif
                        <h1 class="truncate font-display text-3xl font-light tracking-tight sm:text-4xl">{{ $heading }}</h1>
                        @if ($description)
                            <p class="mt-1 text-sm text-zinc-500">{{ $description }}</p>
                        @endif
                    </div>
                    @if ($actions)
                        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                    @endif
                </div>
            @endif

            <x-alerts />
            {{ $slot }}
        </main>
    </div>
</body>
</html>
