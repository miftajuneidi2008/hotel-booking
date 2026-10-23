<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A charge that was moved to another guest bill. */
#[Fillable(['charge_id', 'from_booking_id', 'to_booking_id', 'reason', 'moved_by'])]
class FolioTransfer extends Model
{
    /** @return BelongsTo<FolioCharge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(FolioCharge::class, 'charge_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function fromBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'from_booking_id')->withTrashed();
    }

    /** @return BelongsTo<Booking, $this> */
    public function toBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'to_booking_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
