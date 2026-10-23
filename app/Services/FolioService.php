<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\DiscountType;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\FolioCharge;
use App\Models\FolioDiscount;
use App\Models\FolioTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * The guest bill (folio), section 5.4.
 *
 * - FR-15 room nights come straight from the booking's rate_breakdown (see summary()).
 * - FR-16 extra charges, FR-17 discounts, FR-18 moving and splitting charges.
 *
 * Every change locks the booking inside a transaction, like StayService does, so two people at
 * the desk cannot, for example, void the same charge twice. Nothing is deleted: charges and
 * discounts are voided with a reason and the user who did it (business rules 3, 6 and 7).
 */
class FolioService
{
    /**
     * Everything the folio screens and the printouts show.
     *
     * @return array{
     *     room_lines: list<array{night: string, amount: float}>,
     *     charges: EloquentCollection<int, FolioCharge>,
     *     discounts: EloquentCollection<int, FolioDiscount>,
     *     room_total: float, extras_total: float, gross: float, discounts_total: float,
     *     net: float, paid: float, balance: float
     * }
     */
    public function summary(Booking $booking): array
    {
        $charges = $booking->folioCharges()->with(['creator', 'voider', 'voidApprover'])->orderBy('id')->get();
        $discounts = $booking->folioDiscounts()->with(['creator', 'approver', 'voider', 'voidApprover'])->orderBy('id')->get();

        $roomLines = [];

        foreach ($booking->rate_breakdown ?? [] as $night => $amount) {
            $roomLines[] = ['night' => (string) $night, 'amount' => (float) $amount];
        }

        $roomTotal = round((float) $booking->total_amount, 2);
        $extras = round((float) $charges->whereNull('voided_at')->sum('amount'), 2);
        $discountsTotal = round((float) $discounts->whereNull('voided_at')->sum('amount'), 2);
        $gross = round($roomTotal + $extras, 2);
        $net = round($gross - $discountsTotal, 2);
        $paid = $booking->amountPaid();

        return [
            'room_lines' => $roomLines,
            'charges' => $charges,
            'discounts' => $discounts,
            'room_total' => $roomTotal,
            'extras_total' => $extras,
            'gross' => $gross,
            'discounts_total' => $discountsTotal,
            'net' => $net,
            'paid' => $paid,
            'balance' => round($net - $paid, 2),
        ];
    }

    // ------------------------------------------------------------------
    // FR-16 Extra charges
    // ------------------------------------------------------------------

    /**
     * @param  array{category: string, description: string, quantity: int|string, unit_price: float|int|string}  $data
     *
     * @throws BookingException
     */
    public function addCharge(Booking $booking, array $data, User $by): FolioCharge
    {
        return DB::transaction(function () use ($booking, $data, $by): FolioCharge {
            $fresh = $this->lock($booking->id);

            if (! $fresh->folioAcceptsCharges()) {
                throw new BookingException('Charges can only be added while the guest is checked in.');
            }

            $quantity = (int) $data['quantity'];
            $unitPrice = round((float) $data['unit_price'], 2);

            if ($quantity < 1 || $unitPrice <= 0) {
                throw new BookingException('The quantity and the price must be more than zero.');
            }

            return $fresh->folioCharges()->create([
                'category' => $data['category'],
                'description' => trim((string) $data['description']),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => round($quantity * $unitPrice, 2),
                'created_by' => $by->id,
            ]);
        });
    }

    /**
     * Void a wrong charge. A charge above the owner's limit needs a manager or the owner.
     *
     * @throws BookingException
     */
    public function voidCharge(FolioCharge $charge, string $reason, User $by): FolioCharge
    {
        return DB::transaction(function () use ($charge, $reason, $by): FolioCharge {
            $booking = $this->lock($charge->booking_id);
            $fresh = FolioCharge::query()->whereKey($charge->id)->lockForUpdate()->firstOrFail();

            if (! $booking->folioAcceptsCharges()) {
                throw new BookingException('The bill is closed. Charges can only be voided while the guest is checked in.');
            }

            if ($fresh->isVoided()) {
                throw new BookingException('This charge is already voided.');
            }

            $reason = $this->requireReason($reason, 'Enter the reason for voiding this charge.');
            $needsApproval = $this->voidNeedsApproval((float) $fresh->amount);

            if ($needsApproval && ! $by->canApproveCheckout()) {
                throw new BookingException('Voiding a charge of ETB '.number_format((float) $fresh->amount, 2)
                    .' needs a manager or the owner.');
            }

            $this->assertPaymentsFit($booking, $booking->folioTotal() - (float) $fresh->amount);

            $fresh->update([
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => $reason,
                'void_approved_by' => $needsApproval ? $by->id : null,
            ]);

            return $fresh;
        });
    }

    // ------------------------------------------------------------------
    // FR-17 Discounts
    // ------------------------------------------------------------------

    /**
     * The discount in ETB for what the guest owes now (room + extras). A percent discount is
     * fixed at this amount; later charges do not change it.
     */
    public function discountAmount(Booking $booking, DiscountType $type, float $value): float
    {
        return $type === DiscountType::Percent
            ? round($booking->grossTotal() * $value / 100, 2)
            : round($value, 2);
    }

    /**
     * Apply a discount with a reason. If all discounts together go over the owner's limit, only
     * a manager or the owner can apply it, and their name is saved as the approver.
     *
     * @throws BookingException
     */
    public function applyDiscount(Booking $booking, DiscountType $type, float $value, string $reason, User $by): FolioDiscount
    {
        return DB::transaction(function () use ($booking, $type, $value, $reason, $by): FolioDiscount {
            $fresh = $this->lock($booking->id);

            if (! $fresh->folioAcceptsDiscounts()) {
                throw new BookingException('The bill is closed, so a discount cannot be added.');
            }

            $reason = $this->requireReason($reason, 'Enter the reason for the discount.');

            if ($value <= 0) {
                throw new BookingException('The discount must be more than zero.');
            }

            if ($type === DiscountType::Percent && $value > 100) {
                throw new BookingException('A percent discount cannot be more than 100%.');
            }

            $gross = $fresh->grossTotal();
            $existing = $fresh->discountsTotal();
            $amount = $this->discountAmount($fresh, $type, $value);

            if ($amount <= 0) {
                throw new BookingException('That discount is ETB 0.00. There is nothing to discount yet.');
            }

            if ($existing + $amount > $gross + 0.004) {
                throw new BookingException('The discounts would be more than the bill (ETB '.number_format($gross, 2)
                    .', with ETB '.number_format($existing, 2).' already discounted).');
            }

            $this->assertPaymentsFit($fresh, $gross - $existing - $amount);

            $needsApproval = $this->discountNeedsApproval($gross, $existing + $amount);

            if ($needsApproval && ! $by->canApproveCheckout()) {
                throw new BookingException('This discount is over the limit set by the owner. A manager or the owner has to apply it.');
            }

            return $fresh->folioDiscounts()->create([
                'type' => $type,
                'value' => round($value, 2),
                'amount' => $amount,
                'reason' => $reason,
                'needs_approval' => $needsApproval,
                'approved_by' => $needsApproval ? $by->id : null,
                'created_by' => $by->id,
            ]);
        });
    }

    /**
     * Remove a discount (the bill goes back up). One that needed approval needs it again to remove.
     *
     * @throws BookingException
     */
    public function voidDiscount(FolioDiscount $discount, string $reason, User $by): FolioDiscount
    {
        return DB::transaction(function () use ($discount, $reason, $by): FolioDiscount {
            $booking = $this->lock($discount->booking_id);
            $fresh = FolioDiscount::query()->whereKey($discount->id)->lockForUpdate()->firstOrFail();

            if (! $booking->folioAcceptsDiscounts()) {
                throw new BookingException('The bill is closed, so the discount cannot be removed.');
            }

            if ($fresh->isVoided()) {
                throw new BookingException('This discount is already removed.');
            }

            $reason = $this->requireReason($reason, 'Enter the reason for removing the discount.');

            if ($fresh->needs_approval && ! $by->canApproveCheckout()) {
                throw new BookingException('This discount was approved by a manager, so a manager or the owner has to remove it.');
            }

            $fresh->update([
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => $reason,
                'void_approved_by' => $fresh->needs_approval ? $by->id : null,
            ]);

            return $fresh;
        });
    }

    // ------------------------------------------------------------------
    // FR-18 Move or split a charge
    // ------------------------------------------------------------------

    /**
     * Other guests who are in the house and can take a charge.
     *
     * @return EloquentCollection<int, Booking>
     */
    public function transferTargets(Booking $source): EloquentCollection
    {
        return Booking::query()
            ->where('status', BookingStatus::CheckedIn->value)
            ->whereKeyNot($source->id)
            ->with('room')
            ->orderBy('guest_name')
            ->get();
    }

    /**
     * Move a charge to another guest's bill. If $amount is less than the charge, only that part
     * moves: the charge is voided as "split" and replaced by two lines (what stays and what moves),
     * so the history stays visible and the two bills still add up to the same money.
     *
     * @throws BookingException
     */
    public function transferCharge(FolioCharge $charge, Booking $target, float $amount, string $reason, User $by): FolioCharge
    {
        return DB::transaction(function () use ($charge, $target, $amount, $reason, $by): FolioCharge {
            // Lock both bills in id order so two moves in opposite directions cannot deadlock.
            $ids = collect([$charge->booking_id, $target->id])->unique()->sort()->values();
            $locked = $ids->mapWithKeys(fn (int $id): array => [$id => $this->lock($id)]);

            $source = $locked[$charge->booking_id];
            $destination = $locked[$target->id];
            $fresh = FolioCharge::query()->whereKey($charge->id)->lockForUpdate()->firstOrFail();

            if ($fresh->booking_id !== $source->id) {
                throw new BookingException('This charge was just moved. Reload the bill and try again.');
            }

            if ($source->id === $destination->id) {
                throw new BookingException('Choose a different guest bill.');
            }

            if (! $source->folioAcceptsCharges() || ! $destination->folioAcceptsCharges()) {
                throw new BookingException('Charges can only be moved between guests who are checked in.');
            }

            if ($fresh->isVoided()) {
                throw new BookingException('A voided charge cannot be moved.');
            }

            $reason = $this->requireReason($reason, 'Enter the reason for moving the charge.');
            $amount = round($amount, 2);
            $full = (float) $fresh->amount;

            if ($amount <= 0 || $amount > $full + 0.004) {
                throw new BookingException('The amount to move must be more than zero and not more than ETB '.number_format($full, 2).'.');
            }

            $this->assertPaymentsFit($source, $source->folioTotal() - $amount);

            if ($amount >= $full - 0.004) {
                $fresh->update(['booking_id' => $destination->id]);
                FolioTransfer::create([
                    'charge_id' => $fresh->id,
                    'from_booking_id' => $source->id,
                    'to_booking_id' => $destination->id,
                    'reason' => $reason,
                    'moved_by' => $by->id,
                ]);

                return $fresh;
            }

            $fresh->update([
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => 'Split: ETB '.number_format($amount, 2).' moved to '.$destination->reference.'. '.$reason,
                'void_was_split' => true,
            ]);

            $line = fn (Booking $booking, float $part): FolioCharge => $booking->folioCharges()->create([
                'split_from_id' => $fresh->id,
                'category' => $fresh->category,
                'description' => $fresh->description,
                'quantity' => 1,
                'unit_price' => $part,
                'amount' => $part,
                'created_by' => $by->id,
            ]);

            $line($source, round($full - $amount, 2));
            $moved = $line($destination, $amount);

            FolioTransfer::create([
                'charge_id' => $moved->id,
                'from_booking_id' => $source->id,
                'to_booking_id' => $destination->id,
                'reason' => $reason,
                'moved_by' => $by->id,
            ]);

            return $moved;
        });
    }

    // ------------------------------------------------------------------
    // Approval rules (business rule 3)
    // ------------------------------------------------------------------

    /** Is this much discount in total (ETB) over the owner's limits? */
    public function discountNeedsApproval(float $gross, float $discountTotal): bool
    {
        $percentLimit = config('folio.discount_approval.percent');
        $amountLimit = config('folio.discount_approval.amount');

        if ($percentLimit !== null && $gross > 0 && ($discountTotal / $gross * 100) > (float) $percentLimit + 0.0001) {
            return true;
        }

        return $amountLimit !== null && $discountTotal > (float) $amountLimit + 0.004;
    }

    public function voidNeedsApproval(float $amount): bool
    {
        $limit = config('folio.void_approval_amount');

        return $limit !== null && $amount > (float) $limit + 0.004;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function lock(int $bookingId): Booking
    {
        return Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();
    }

    private function requireReason(string $reason, string $message): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new BookingException($message);
        }

        return $reason;
    }

    /** A change must never leave the guest having paid more than the new bill (refunds come with section 5.5). */
    private function assertPaymentsFit(Booking $booking, float $newNet): void
    {
        if ($booking->amountPaid() > round($newNet, 2) + 0.004) {
            throw new BookingException('The guest has already paid ETB '.number_format($booking->amountPaid(), 2)
                .', which is more than the new bill (ETB '.number_format(max($newNet, 0), 2).'). Refund the difference first.');
        }
    }
}
