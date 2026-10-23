<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;

/**
 * Who may work with invoices (section 5.9).
 * Desk staff issue and list invoices; only a manager or the owner may void one (business rule 3).
 * The restaurant/cashier role keeps what it had before: it may open an invoice, nothing more.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageBookings();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->canManageBookings() || $user->role === UserRole::Cashier;
    }

    public function create(User $user): bool
    {
        return $user->canManageBookings();
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->canManageProperty();
    }
}
