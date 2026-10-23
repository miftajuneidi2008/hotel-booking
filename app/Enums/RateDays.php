<?php

namespace App\Enums;

enum RateDays: string
{
    case AllDays = 'all_days';
    case Weekends = 'weekends';

    public function label(): string
    {
        return match ($this) {
            self::AllDays => 'Every night',
            self::Weekends => 'Weekend nights (Fri & Sat)',
        };
    }
}
