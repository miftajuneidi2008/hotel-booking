<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /** A guest sees their own bookings; booking staff see all of them. */
    public function view(User $user, Booking $booking): bool
    {
        return $user->canManageBookings() || $booking->user_id === $user->id;
    }

    /** Only a confirmed booking can be cancelled, by its owner or by booking staff. */
    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->isConfirmed() && $this->view($user, $booking);
    }
}
