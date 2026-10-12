<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum PlanInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensual',
            self::Yearly => 'Anual',
        };
    }

    public function perLabel(): string
    {
        return match ($this) {
            self::Monthly => '/ mes',
            self::Yearly => '/ año',
        };
    }

    public function addTo(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Monthly => $date->copy()->addMonthNoOverflow(),
            self::Yearly => $date->copy()->addYearNoOverflow(),
        };
    }
}
