<x-mail::message>
# Tu acceso vence pronto

Tu suscripción vence el **{{ $subscription->ends_at->translatedFormat('j \d\e F \d\e Y') }}**.

Para seguir leyendo sin interrupciones, renueva antes de esa fecha. El nuevo periodo se suma al tiempo que te queda.

<x-mail::button :url="route('checkout.create')">
Renovar ahora
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
