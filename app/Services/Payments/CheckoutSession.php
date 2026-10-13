<?php

namespace App\Services\Payments;

use App\Models\Payment;

final readonly class CheckoutSession
{
    public function __construct(public Payment $payment, public string $url) {}
}
