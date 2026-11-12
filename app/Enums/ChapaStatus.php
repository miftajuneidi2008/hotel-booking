<?php

namespace App\Enums;

enum ChapaStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    /** Chapa took the money, but it could not be put on the bill automatically. Staff must look at it. */
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Success => 'Paid',
            self::Failed => 'Failed',
            self::Review => 'Needs review',
        };
    }

    /** Success and Review are final: they are never checked or posted again. */
    public function isSettled(): bool
    {
        return $this === self::Success || $this === self::Review;
    }
}
