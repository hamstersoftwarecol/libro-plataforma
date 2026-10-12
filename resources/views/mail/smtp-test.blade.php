<x-mail::message>
# El correo funciona

Este es un mensaje de prueba enviado desde la configuración SMTP del panel de {{ config('app.name') }}.

Si lo recibiste, los códigos de acceso, recibos y avisos también llegarán.

Servidor: {{ config('mail.mailers.smtp.host') }}:{{ config('mail.mailers.smtp.port') }}

{{ config('app.name') }}
</x-mail::message>
