<x-mail::message>
# Tu código de acceso

Usa este código para ingresar a {{ config('app.name') }}:

<x-mail::panel>
<div style="font-size: 28px; letter-spacing: 8px; text-align: center; font-weight: bold;">{{ $code }}</div>
</x-mail::panel>

El código vence en {{ $expiresInMinutes }} minutos y solo puede usarse una vez.

Si no solicitaste este código, ignora este mensaje.

{{ config('app.name') }}
</x-mail::message>
