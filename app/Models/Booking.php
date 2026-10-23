<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\IdType;
use App\Enums\PaymentMethod;
use App\Models\Concerns\HasFolio;
use App\Models\Concerns\HasPayments;
use App\Support\PropertyTime;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'reference', 'room_id', 'user_id', 'booking_group_id', 'created_by', 'source', 'status',
    'check_in', 'check_out', 'guests',
    'guest_name', 'guest_phone', 'guest_email', 'notes',
    'total_amount', 'rate_breakdown',
    'deposit_amount', 'deposit_method', 'deposit_reference',
    'id_type', 'id_number', 'nationality', 'checked_in_by',
    'settlement_amount', 'settlement_method', 'settlement_reference',
    'checked_out_at', 'checked_out_by', 'checkout_unpaid_amount', 'checkout_override_reason', 'checkout_approved_by',
    'checked_in_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, HasFolio, HasPayments, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            $booking->reference ??= static::newReference();
        });
    }

    /**
     * A short code the guest can read out at the desk, e.g. BK-7H3K9Q.
     * Letters and digits that are easy to confuse (0/O, 1/I) are left out.
     */
    public static function newReference(): string
    {
        do {
            $reference = 'BK-'.collect(str_split('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'))
                ->random(6)
                ->implode('');
        } while (static::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'source' => BookingSource::class,
            'status' => BookingStatus::class,
            'deposit_method' => PaymentMethod::class,
            'check_in' => 'date',
            'check_out' => 'date',
            'guests' => 'integer',
            'total_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'rate_breakdown' => 'array',
            'id_type' => IdType::class,
            'settlement_method' => PaymentMethod::class,
            'settlement_amount' => 'decimal:2',
            'checkout_unpaid_amount' => 'decimal:2',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    /** @return BelongsTo<BookingGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(BookingGroup::class, 'booking_group_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Bookings that hold their room (confirmed or checked in).
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', array_map(fn (BookingStatus $s): string => $s->value, BookingStatus::blocking()));
    }

    /**
     * Bookings whose nights overlap the stay [$checkIn, $checkOut).
     *
     * The check-out day is free: a guest leaving on the 10th does not block a
     * guest arriving on the 10th. whereDate() is used so the comparison works
     * whether the date is stored as "2026-10-10" or "2026-10-10 00:00:00".
     *
     * A guest who is still checked in AFTER their check-out date (an overstay) keeps the room:
     * it stays taken for today until the guest really checks out, so nobody else can book it.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeOverlapping(Builder $query, string $checkIn, string $checkOut): void
    {
        $today = PropertyTime::today();

        $query->whereDate('check_in', '<', $checkOut)
            ->where(function (Builder $dates) use ($checkIn, $today): void {
                $dates->whereDate('check_out', '>', $checkIn);

                if ($checkIn <= $today) {
                    $dates->orWhere(fn (Builder $overdue) => $overdue
                        ->where('status', BookingStatus::CheckedIn->value)
                        ->whereDate('check_out', '<', $today));
                }
            });
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    /** Money received so far: the deposit, anything paid at check-out, and payments minus refunds (section 5.5). */
    public function amountPaid(): float
    {
        return round((float) $this->deposit_amount + (float) $this->settlement_amount + $this->paymentsNet(), 2);
    }

    public function balanceDue(): float
    {
        // Room nights + extra charges - discounts (guest bill, section 5.4) - money received.
        return round($this->folioTotal() - $this->amountPaid(), 2);
    }

    public function isCheckedIn(): bool
    {
        return $this->status === BookingStatus::CheckedIn;
    }

    /** @return HasMany<RoomMove, $this> */
    public function roomMoves(): HasMany
    {
        return $this->hasMany(RoomMove::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /** @return BelongsTo<User, $this> */
    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    /** @return BelongsTo<User, $this> */
    public function checkoutApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checkout_approved_by');
    }

    public function isConfirmed(): bool
    {
        return $this->status === BookingStatus::Confirmed;
    }

    public function shortReference(): string
    {
        return Str::upper($this->reference);
    }
}
