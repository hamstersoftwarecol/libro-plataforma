<x-mail::message>
# Gracias por tu pago

Confirmamos tu pago. Tu acceso está activo hasta el **{{ $payment->subscription?->ends_at->translatedFormat('j \d\e F \d\e Y') }}**.

<x-mail::table>
| Concepto | Detalle |
|:--|--:|
| Plan | {{ $payment->plan->name }} ({{ $payment->plan->interval->label() }}) |
| Monto | {{ $payment->formattedAmount() }} |
| Fecha | {{ $payment->paid_at->format('d/m/Y H:i') }} |
| Referencia | {{ $payment->reference }} |
</x-mail::table>

<x-mail::button :url="route('library')">
Ir a la biblioteca
</x-mail::button>

Puedes ver todos tus pagos en [Mi cuenta]({{ route('account') }}).

{{ config('app.name') }}
</x-mail::message>
