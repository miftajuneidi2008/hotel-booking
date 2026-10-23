<?php

namespace App\Enums;

enum RoomStatus: string
{
    case Clean = 'clean';
    case Dirty = 'dirty';
    case Occupied = 'occupied';
    case OutOfService = 'out_of_service';

    public function label(): string
    {
        return match ($this) {
            self::Clean => 'Clean',
            self::Dirty => 'Dirty',
            self::Occupied => 'Occupied',
            self::OutOfService => 'Out of service',
        };
    }
}
