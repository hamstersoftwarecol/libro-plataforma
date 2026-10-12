@php
    $singular = $type === 'categories' ? 'categoría' : 'autor';
    $descriptionField = $type === 'categories' ? 'description' : 'bio';
@endphp

<x-admin-layout :title="$title" :heading="$title" :description="$type === 'categories' ? 'Organiza el catálogo por temas.' : 'Autores disponibles en el catálogo.'">
    <form method="POST" action="{{ route("admin.$type.store") }}" class="card card-body mb-6 flex flex-wrap items-end gap-3">
        @csrf
        <div class="min-w-48 flex-1">
            <label class="label">Nombre</label>
            <input name="name" required value="{{ old('name') }}" class="input" placeholder="{{ $type === 'categories' ? 'Ej.: Novela' : 'Ej.: Isabel Allende' }}">
        </div>
        <div class="min-w-60 flex-[2]">
            <label class="label">{{ $type === 'categories' ? 'Descripción' : 'Biografía' }}</label>
            <input name="description" value="{{ old('description') }}" class="input" placeholder="Opcional">
        </div>
        <button class="btn btn-primary"><x-icon name="plus" class="size-4" />Agregar {{ $singular }}</button>
    </form>

    <div class="card divide-y divide-zinc-100 dark:divide-zinc-800">
        @forelse ($items as $item)
            <div class="flex flex-wrap items-center gap-3 p-4">
                <form method="POST" action="{{ route("admin.$type.update", $item) }}" class="flex flex-1 flex-wrap gap-2">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $item->name }}" required class="input input-sm w-56 font-medium">
                    <input name="description" value="{{ $item->{$descriptionField} }}" class="input input-sm min-w-48 flex-1" placeholder="Sin descripción">
                    <button class="btn btn-secondary btn-sm">Guardar</button>
                </form>
                <span class="badge bg-zinc-50 text-zinc-600 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700">{{ $item->books_count }} {{ $item->books_count === 1 ? 'libro' : 'libros' }}</span>
                <form method="POST" action="{{ route("admin.$type.destroy", $item) }}" onsubmit="return confirm('¿Eliminar {{ $singular }}?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-ghost btn-sm text-zinc-400 hover:text-red-600" title="Eliminar"><x-icon name="trash" class="size-4" /></button>
                </form>
            </div>
        @empty
            <x-empty-state :icon="$type === 'categories' ? 'folder' : 'pen'" title="Aún no hay registros" />
        @endforelse
    </div>

    <div class="mt-6">{{ $items->links() }}</div>
</x-admin-layout>
