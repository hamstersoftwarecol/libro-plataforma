<x-mail::message>
# No pudimos procesar tu pago

El pago de **{{ $payment->formattedAmount() }}** por el plan {{ $payment->plan->name }} fue rechazado.

@if ($payment->failure_reason)
Motivo: {{ $payment->failure_reason }}
@endif

No se realizó ningún cobro. Puedes intentarlo de nuevo con otro medio de pago.

<x-mail::button :url="route('checkout.create')">
Intentar de nuevo
</x-mail::button>

Referencia: {{ $payment->reference }}

{{ config('app.name') }}
</x-mail::message>
