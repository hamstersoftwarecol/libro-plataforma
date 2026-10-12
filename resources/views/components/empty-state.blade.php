@props(['icon' => 'book', 'title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center px-6 py-16 text-center') }}>
    <div class="grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-brand-100 dark:bg-brand-950 dark:text-brand-300 dark:ring-brand-900">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <h3 class="mt-4 font-semibold">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-zinc-500">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-6">{{ $slot }}</div>
    @endif
</div>
