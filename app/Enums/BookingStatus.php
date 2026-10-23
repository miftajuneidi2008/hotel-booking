<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked in',
            self::CheckedOut => 'Checked out',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Statuses that hold a room for their dates (used by the double-booking check).
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::Confirmed, self::CheckedIn];
    }
}
