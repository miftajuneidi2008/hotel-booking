<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Receptionist = 'receptionist';
    case Housekeeping = 'housekeeping';
    case Cashier = 'cashier';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Receptionist => 'Receptionist',
            self::Housekeeping => 'Housekeeping',
            self::Cashier => 'Restaurant / cashier',
            self::Guest => 'Guest',
        };
    }

    /** Roles that may edit rooms, room types and rates. */
    public function canManageProperty(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** Roles that may approve leaving with an unpaid balance (business rule 4). */
    public function canApproveCheckout(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** Roles that may create and cancel bookings for guests at the desk. */
    public function canManageBookings(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Receptionist], true);
    }
}
