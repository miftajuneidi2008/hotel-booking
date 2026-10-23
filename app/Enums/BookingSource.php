<?php

namespace App\Enums;

enum BookingSource: string
{
    case Online = 'online';
    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online (guest)',
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone',
            self::Agent => 'Agent',
        };
    }

    /**
     * Sources a receptionist may choose at the desk.
     *
     * @return list<self>
     */
    public static function desk(): array
    {
        return [self::WalkIn, self::Phone, self::Agent];
    }
}
