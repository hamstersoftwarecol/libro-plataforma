<?php

namespace App\Services\Payments;

final readonly class GatewayEvent
{
    public const PAYMENT_SUCCEEDED = 'payment.succeeded';

    public const PAYMENT_FAILED = 'payment.failed';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $reference,
        public ?string $gatewayPaymentId,
        public int $amount,
        public string $currency,
        public ?string $failureReason,
        public array $payload,
    ) {}
}
