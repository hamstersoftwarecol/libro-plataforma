<x-mail::message>
# Renovación cancelada

Tu suscripción no se renovará. Seguirás teniendo acceso a todo el catálogo hasta el **{{ $subscription->ends_at->translatedFormat('j \d\e F \d\e Y') }}**.

Si cambias de opinión, puedes reactivarla en cualquier momento antes de esa fecha.

<x-mail::button :url="route('account')">
Ir a Mi cuenta
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
