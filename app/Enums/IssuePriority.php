<?php

namespace App\Enums;

enum IssuePriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::Urgent => 'Urgent',
        };
    }

    /** Tailwind classes for the badge. */
    public function badge(): string
    {
        return match ($this) {
            self::Low => 'bg-gray-100 text-gray-700',
            self::Normal => 'bg-blue-100 text-blue-800',
            self::Urgent => 'bg-red-100 text-red-800',
        };
    }
}
