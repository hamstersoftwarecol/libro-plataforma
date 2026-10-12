@if (session('status'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl bg-emerald-50 px-4 py-3.5 text-sm text-emerald-900 ring-1 ring-emerald-200 animate-fade-up dark:bg-emerald-950/50 dark:text-emerald-200 dark:ring-emerald-900" role="status">
        <x-icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
        <p>{{ session('status') }}</p>
    </div>
@endif

@if ($errors->any() && ! $errors->has('email') && ! $errors->has('code'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl bg-red-50 px-4 py-3.5 text-sm text-red-900 ring-1 ring-red-200 animate-fade-up dark:bg-red-950/50 dark:text-red-200 dark:ring-red-900" role="alert">
        <x-icon name="warning" class="size-5 text-red-600 dark:text-red-400" />
        <ul class="space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
