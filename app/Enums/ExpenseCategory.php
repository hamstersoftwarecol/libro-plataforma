<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Hosting = 'hosting';
    case Royalties = 'royalties';
    case Marketing = 'marketing';
    case PaymentFees = 'payment_fees';
    case Software = 'software';
    case Payroll = 'payroll';
    case Taxes = 'taxes';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Hosting => 'Servidores y hosting',
            self::Royalties => 'Derechos de autor y licencias',
            self::Marketing => 'Marketing y publicidad',
            self::PaymentFees => 'Comisiones de pago',
            self::Software => 'Software y servicios',
            self::Payroll => 'Nómina y honorarios',
            self::Taxes => 'Impuestos',
            self::Other => 'Otros',
        };
    }
}
