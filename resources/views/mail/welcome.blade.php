<x-mail::message>
# ¡Bienvenido!

Tu pago fue confirmado y tu cuenta en {{ config('app.name') }} ya está lista.

No necesitas contraseña: cuando quieras volver, entra en **Ingresar** con este correo y te enviaremos un código de acceso.

<x-mail::button :url="route('login')">
Ingresar a mi biblioteca
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
