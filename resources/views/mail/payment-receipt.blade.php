<x-mail::message>
# Gracias por tu pago

Confirmamos tu pago. {{ $payment->book ? 'El libro es tuyo para siempre: puedes leerlo cuando quieras desde tu navegador.' : 'Tu acceso está activo hasta el '.$payment->subscription?->ends_at->translatedFormat('j \d\e F \d\e Y').'.' }}

<x-mail::table>
| Concepto | Detalle |
|:--|--:|
| Compra | {{ $payment->description() }} |
| Monto | {{ $payment->formattedAmount() }} |
| Fecha | {{ $payment->paid_at->format('d/m/Y H:i') }} |
| Referencia | {{ $payment->reference }} |
</x-mail::table>

<x-mail::button :url="route('book.start')">
Empezar a leer
</x-mail::button>

Puedes ver todos tus pagos en [Mi cuenta]({{ route('account') }}).

{{ config('app.name') }}
</x-mail::message>
