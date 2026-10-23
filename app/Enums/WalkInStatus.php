<?php

namespace App\Enums;

enum WalkInStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Transferred = 'transferred';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Paid => 'Paid',
            self::Transferred => 'Moved to room bill',
            self::Voided => 'Voided',
        };
    }

    /** Tailwind classes for the badge. */
    public function badge(): string
    {
        return match ($this) {
            self::Open => 'bg-amber-100 text-amber-800',
            self::Paid => 'bg-emerald-100 text-emerald-800',
            self::Transferred => 'bg-sky-100 text-sky-800',
            self::Voided => 'bg-gray-200 text-gray-600',
        };
    }
}
