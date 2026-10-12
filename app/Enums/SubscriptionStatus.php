<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case PastDue = 'past_due';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Vencida',
            self::PastDue => 'Pago pendiente',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'badge badge-dot bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-900',
            self::Cancelled => 'badge badge-dot bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-900',
            self::PastDue => 'badge badge-dot bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/60 dark:text-red-300 dark:ring-red-900',
            self::Expired => 'badge badge-dot bg-zinc-50 text-zinc-600 ring-zinc-200 dark:bg-zinc-800/60 dark:text-zinc-300 dark:ring-zinc-700',
        };
    }
}
