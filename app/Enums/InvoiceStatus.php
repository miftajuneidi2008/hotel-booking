<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Issued = 'issued';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::Voided => 'Voided',
        };
    }

    /** Tailwind classes for the badge. */
    public function badge(): string
    {
        return match ($this) {
            self::Issued => 'bg-emerald-100 text-emerald-800',
            self::Voided => 'bg-gray-200 text-gray-600',
        };
    }
}
