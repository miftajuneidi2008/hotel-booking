<?php

namespace App\Enums;

enum IdType: string
{
    case NationalId = 'national_id';
    case Passport = 'passport';
    case DrivingLicence = 'driving_licence';
    case ResidencePermit = 'residence_permit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => 'National ID (Kebele ID)',
            self::Passport => 'Passport',
            self::DrivingLicence => 'Driving licence',
            self::ResidencePermit => 'Residence permit',
            self::Other => 'Other',
        };
    }
}
