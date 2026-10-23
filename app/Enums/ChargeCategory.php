<?php

namespace App\Enums;

enum ChargeCategory: string
{
    case Food = 'food';
    case Laundry = 'laundry';
    case Transport = 'transport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Food => 'Food & drink',
            self::Laundry => 'Laundry',
            self::Transport => 'Transport',
            self::Other => 'Other',
        };
    }
}
