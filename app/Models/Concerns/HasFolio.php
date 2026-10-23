<?php

namespace App\Models\Concerns;

use App\Models\FolioCharge;
use App\Models\FolioDiscount;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Guest bill (folio) helpers for a booking (section 5.4).
 *
 * The room nights stay in bookings.total_amount / rate_breakdown. Extra charges and discounts
 * live in their own tables, so changing or moving a stay never touches them.
 */
trait HasFolio
{
    /** @return HasMany<FolioCharge, $this> */
    public function folioCharges(): HasMany
    {
        return $this->hasMany(FolioCharge::class);
    }

    /** @return HasMany<FolioDiscount, $this> */
    public function folioDiscounts(): HasMany
    {
        return $this->hasMany(FolioDiscount::class);
    }

    /** Extra charges that count on the bill (voided ones are left out). */
    public function extrasTotal(): float
    {
        return round((float) $this->folioCharges()->whereNull('voided_at')->sum('amount'), 2);
    }

    public function discountsTotal(): float
    {
        return round((float) $this->folioDiscounts()->whereNull('voided_at')->sum('amount'), 2);
    }

    /** Room nights + extra charges, before discounts. */
    public function grossTotal(): float
    {
        return round((float) $this->total_amount + $this->extrasTotal(), 2);
    }

    /** What the guest has to pay in all: room nights + extras - discounts. */
    public function folioTotal(): float
    {
        return round($this->grossTotal() - $this->discountsTotal(), 2);
    }

    /** Extra charges, moves and voids are only possible while the guest is in the house. */
    public function folioAcceptsCharges(): bool
    {
        return $this->isCheckedIn();
    }

    /** Discounts can also be agreed before arrival. After check-out the bill is closed. */
    public function folioAcceptsDiscounts(): bool
    {
        return $this->isConfirmed() || $this->isCheckedIn();
    }
}
