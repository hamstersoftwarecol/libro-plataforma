<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Bold (bold.co) payment button integration.
 *
 * - The checkout renders Bold's button with a server-side integrity signature.
 * - Webhooks are verified with HMAC-SHA256 over the base64 body (empty key in test mode).
 * - The return page asks Bold's API for the voucher so access is granted without waiting for the webhook.
 */
class BoldGateway implements PaymentGateway, SyncsPaymentStatus
{
    public const SIGNATURE_HEADER = 'x-bold-signature';

    public const SUPPORTED_CURRENCIES = ['COP', 'USD'];

    public const MINIMUM_COP = 1000;

    public function __construct(
        private string $identityKey,
        private string $secretKey,
        private string $environment = 'test',
        private string $apiUrl = 'https://payments.api.bold.co',
    ) {}

    public function name(): string
    {
        return 'bold';
    }

    public function isTestMode(): bool
    {
        return $this->environment !== 'production';
    }

    public function identityKey(): string
    {
        return $this->identityKey;
    }

    /**
     * Our own page that renders Bold's payment button for this payment.
     */
    public function checkoutUrl(Payment $payment): string
    {
        $this->amountFor($payment);

        return URL::temporarySignedRoute('checkout.bold', now()->addHour(), $payment);
    }

    /**
     * Amount as Bold expects it: whole units, no decimals.
     */
    public function amountFor(Payment $payment): int
    {
        $currency = strtoupper($payment->currency);

        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            throw new RuntimeException("Bold no acepta pagos en {$currency}. Usa planes en COP o USD.");
        }

        $major = Money::toMajor($payment->amount, $currency);

        if (floor($major) !== $major) {
            throw new RuntimeException('Bold solo acepta montos sin decimales. Ajusta el precio del plan.');
        }

        if ($currency === 'COP' && $major < self::MINIMUM_COP) {
            throw new RuntimeException('El monto mínimo en Bold es de $1.000 COP.');
        }

        return (int) $major;
    }

    /**
     * SHA256({order-id}{amount}{currency}{secret key}), always computed on the server.
     */
    public function integritySignature(Payment $payment): string
    {
        return hash('sha256', $payment->reference.$this->amountFor($payment).strtoupper($payment->currency).$this->secretKey);
    }

    public function parseWebhook(Request $request): GatewayEvent
    {
        $body = $request->getContent();
        $signingKey = $this->isTestMode() ? '' : $this->secretKey;
        $expected = hash_hmac('sha256', base64_encode($body), $signingKey);

        if (! hash_equals($expected, (string) $request->header(self::SIGNATURE_HEADER))) {
            throw new InvalidWebhookException('Firma de Bold inválida.');
        }

        $data = json_decode($body, true);
        $reference = $data['data']['metadata']['reference'] ?? null;

        if (! is_array($data) || ! isset($data['id'], $data['type'], $data['data']['amount']['total']) || ! $reference) {
            throw new InvalidWebhookException('Evento de Bold incompleto.');
        }

        $currency = strtoupper((string) ($data['data']['amount']['currency'] ?? 'COP'));

        return new GatewayEvent(
            id: (string) $data['id'],
            type: match ($data['type']) {
                'SALE_APPROVED' => GatewayEvent::PAYMENT_SUCCEEDED,
                'SALE_REJECTED' => GatewayEvent::PAYMENT_FAILED,
                default => (string) $data['type'],
            },
            reference: (string) $reference,
            gatewayPaymentId: $data['data']['payment_id'] ?? null,
            amount: Money::toMinor($data['data']['amount']['total'], $currency),
            currency: $currency,
            failureReason: $data['type'] === 'SALE_REJECTED' ? 'Pago rechazado por Bold' : null,
            payload: $data,
        );
    }

    /**
     * Ask Bold for the voucher of this sale (only final states produce an event).
     */
    public function fetchStatus(Payment $payment): ?GatewayEvent
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders(['Authorization' => 'x-api-key '.$this->identityKey])
                ->timeout(10)
                ->get(rtrim($this->apiUrl, '/').'/v2/payment-voucher/'.rawurlencode($payment->reference));
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $status = strtoupper((string) $response->json('payment_status'));
        $type = match ($status) {
            'APPROVED' => GatewayEvent::PAYMENT_SUCCEEDED,
            'REJECTED', 'FAILED', 'VOIDED' => GatewayEvent::PAYMENT_FAILED,
            default => null,
        };

        if (! $type) {
            return null;
        }

        $transactionId = (string) ($response->json('transaction_id') ?? $payment->reference);

        return new GatewayEvent(
            id: 'voucher-'.$transactionId.'-'.$status,
            type: $type,
            reference: $payment->reference,
            gatewayPaymentId: $response->json('transaction_id'),
            amount: Money::toMinor($response->json('total') ?? 0, $payment->currency),
            currency: strtoupper($payment->currency),
            failureReason: $type === GatewayEvent::PAYMENT_FAILED ? match ($status) {
                'VOIDED' => 'Pago anulado en Bold',
                'FAILED' => 'El pago falló en Bold',
                default => 'Pago rechazado por Bold',
            } : null,
            payload: $response->json() ?? [],
        );
    }

    /**
     * Check the identity key against Bold's API. Returns null when valid, or an error message.
     */
    public function testConnection(): ?string
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders(['Authorization' => 'x-api-key '.$this->identityKey])
                ->timeout(10)
                ->get(rtrim($this->apiUrl, '/').'/v2/payment-voucher/prueba-conexion-libro');
        } catch (ConnectionException $exception) {
            return 'No se pudo conectar con Bold: '.$exception->getMessage();
        }

        return match (true) {
            in_array($response->status(), [401, 403], true) => 'Bold rechazó la llave de identidad (HTTP '.$response->status().').',
            $response->serverError() => 'Bold respondió con un error (HTTP '.$response->status().'). Intenta más tarde.',
            default => null,
        };
    }
}
