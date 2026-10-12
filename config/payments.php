<?php

use App\Services\Payments\BoldGateway;
use App\Services\Payments\SandboxGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateway (HU-018)
    |--------------------------------------------------------------------------
    |
    | "sandbox" simulates a hosted checkout and signed webhooks for local
    | development. Real gateways are added as drivers in "gateways".
    |
    */

    'gateway' => env('PAYMENTS_GATEWAY', 'sandbox'),

    'currency' => env('PAYMENTS_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Webhook signature tolerance in seconds (HU-030)
    |--------------------------------------------------------------------------
    */

    'webhook_tolerance' => 300,

    'gateways' => [

        'bold' => [
            'driver' => BoldGateway::class,
            'identity_key' => env('BOLD_IDENTITY_KEY'),
            'secret_key' => env('BOLD_SECRET_KEY'),
            'environment' => env('BOLD_ENVIRONMENT', 'test'),
            'api_url' => 'https://payments.api.bold.co',
            'script_url' => 'https://checkout.bold.co/library/boldPaymentButton.js',
            'enabled' => true,
        ],

        'sandbox' => [
            'driver' => SandboxGateway::class,
            'webhook_secret' => env('SANDBOX_WEBHOOK_SECRET', env('APP_KEY')),
            'enabled' => env('APP_ENV') !== 'production',
        ],

    ],

];
