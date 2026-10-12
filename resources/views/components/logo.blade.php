@props(['size' => 'md'])

<span {{ $attributes->class('notranslate inline-flex items-center gap-2.5') }} translate="no">
    <span @class([
        'relative grid place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-[var(--shadow-glow)]',
        'size-8' => $size === 'md',
        'size-10' => $size === 'lg',
    ])>
        <svg viewBox="0 0 24 24" fill="none" class="{{ $size === 'lg' ? 'size-5' : 'size-4' }}" aria-hidden="true">
            <path d="M4 5.5C4 4.67 4.67 4 5.5 4H11v16H5.5A1.5 1.5 0 014 18.5v-13z" fill="currentColor" opacity=".55"/>
            <path d="M13 4h5.5c.83 0 1.5.67 1.5 1.5v13a1.5 1.5 0 01-1.5 1.5H13V4z" fill="currentColor"/>
        </svg>
    </span>
    <span @class(['font-display font-semibold tracking-tight', 'text-lg' => $size === 'md', 'text-2xl' => $size === 'lg'])>{{ config('app.name') }}</span>
</span>
