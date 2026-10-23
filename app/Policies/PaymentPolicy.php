<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;

/**
 * Who may work with payments (BRD section 3: the receptionist takes payments; the cashier role only adds charges).
 * The refund limit is checked in PaymentService.
 */
class PaymentPolicy
{
    /** Record a payment or a refund. */
    public function create(User $user): bool
    {
        return $user->canManageBookings();
    }

    /** Void a wrong entry: manager and owner only. */
    public function void(User $user, Payment $payment): bool
    {
        return $user->canManageProperty();
    }

    /** The daily cash summary. The restaurant/cashier role sees only its own cash (walk-in bills). */
    public function viewSummary(User $user): bool
    {
        return $user->canManageBookings() || $user->role === UserRole::Cashier;
    }

    /** Count the cash and close your own day. */
    public function closeDay(User $user): bool
    {
        return $this->viewSummary($user);
    }

    /** See everybody's cash, not just your own. */
    public function viewAllSummaries(User $user): bool
    {
        return $user->canManageProperty();
    }
}
