<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Local gateway that simulates a hosted checkout and HMAC-signed webhooks.
 */
class SandboxGateway implements PaymentGateway
{
    public const SIGNATURE_HEADER = 'X-Sandbox-Signature';

    public const TIMESTAMP_HEADER = 'X-Sandbox-Timestamp';

    public function __construct(private string $webhookSecret) {}

    public function name(): string
    {
        return 'sandbox';
    }

    public function checkoutUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute('sandbox.checkout', now()->addMinutes(30), $payment);
    }

    public function parseWebhook(Request $request): GatewayEvent
    {
        $timestamp = (int) $request->header(self::TIMESTAMP_HEADER);
        $expected = $this->signature($timestamp, $request->getContent());

        if (! hash_equals($expected, (string) $request->header(self::SIGNATURE_HEADER))) {
            throw new InvalidWebhookException('Firma inválida.');
        }

        if (abs(now()->timestamp - $timestamp) > config('payments.webhook_tolerance')) {
            throw new InvalidWebhookException('Webhook expirado.');
        }

        $data = $request->json()->all();

        if (! isset($data['id'], $data['type'], $data['reference'], $data['amount'], $data['currency'])) {
            throw new InvalidWebhookException('Payload incompleto.');
        }

        return new GatewayEvent(
            id: (string) $data['id'],
            type: (string) $data['type'],
            reference: (string) $data['reference'],
            gatewayPaymentId: $data['payment_id'] ?? null,
            amount: (int) $data['amount'],
            currency: (string) $data['currency'],
            failureReason: $data['failure_reason'] ?? null,
            payload: $data,
        );
    }

    /**
     * Build a signed webhook request, as the gateway would send it.
     *
     * @param  array<string, mixed>  $payload
     * @return array{body: string, headers: array<string, string>}
     */
    public function signedWebhook(array $payload, ?int $timestamp = null): array
    {
        $timestamp ??= now()->timestamp;
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                self::TIMESTAMP_HEADER => (string) $timestamp,
                self::SIGNATURE_HEADER => $this->signature($timestamp, $body),
            ],
        ];
    }

    private function signature(int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $this->webhookSecret);
    }
}
