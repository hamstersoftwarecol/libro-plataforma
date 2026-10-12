<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * Resolve a configured and enabled gateway driver.
     */
    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= config('payments.gateway');
        $config = config("payments.gateways.{$name}");

        if (! $config || ! ($config['enabled'] ?? true)) {
            throw new InvalidArgumentException("Pasarela de pago no disponible: {$name}");
        }

        return match ($config['driver']) {
            SandboxGateway::class => new SandboxGateway((string) $config['webhook_secret']),
            BoldGateway::class => new BoldGateway(
                (string) $config['identity_key'],
                (string) $config['secret_key'],
                (string) ($config['environment'] ?? 'test'),
                (string) ($config['api_url'] ?? 'https://payments.api.bold.co'),
            ),
            default => app($config['driver']),
        };
    }
}
