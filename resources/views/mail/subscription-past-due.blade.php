<x-mail::message>
# Tu suscripción venció

Tu periodo terminó el {{ $subscription->ends_at->translatedFormat('j \d\e F') }} y no recibimos el pago de renovación, así que pausamos tu acceso a los libros.

Tu progreso de lectura está guardado. Renueva para continuar donde lo dejaste.

<x-mail::button :url="route('checkout.create')">
Renovar suscripción
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
