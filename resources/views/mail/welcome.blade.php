<x-mail::message>
# ¡Bienvenido!

Tu cuenta en {{ config('app.name') }} está lista. No necesitas contraseña: cada vez que quieras entrar, te enviaremos un código a este correo.

<x-mail::button :url="route('plans.index')">
Ver planes
</x-mail::button>

Con una suscripción activa puedes leer todo el catálogo desde tu navegador.

{{ config('app.name') }}
</x-mail::message>
