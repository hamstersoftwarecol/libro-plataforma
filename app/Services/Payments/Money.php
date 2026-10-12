<?php

namespace App\Services\Payments;

use Illuminate\Support\Number;

class Money
{
    /**
     * Currencies whose amounts are stored without minor units.
     */
    private const ZERO_DECIMAL = ['COP', 'CLP', 'JPY', 'PYG'];

    /**
     * Format an amount stored in minor units (e.g. cents).
     */
    public static function format(int $amount, string $currency): string
    {
        return (string) Number::currency(self::toMajor($amount, $currency), in: $currency, locale: 'es', precision: self::decimals($currency));
    }

    public static function toMajor(int $amount, string $currency): float
    {
        return $amount / (10 ** self::decimals($currency));
    }

    public static function toMinor(float|int|string $amount, string $currency): int
    {
        return (int) round((float) $amount * (10 ** self::decimals($currency)));
    }

    public static function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? 0 : 2;
    }
}
