<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\WalkInStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\FolioCharge;
use App\Models\MenuItem;
use App\Models\User;
use App\Models\WalkInBill;
use App\Models\WalkInBillLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Restaurant and extras, section 5.7.
 *
 * - FR-27: the menu (MenuItem).
 * - FR-28: put menu items on a hotel guest's room bill (through FolioService, so every charge follows the
 *   folio rules), or on a walk-in bill for a customer who is not a guest.
 *
 * Prices are copied onto the bill when the item is sold, so a later price change never changes an old bill
 * (business rule 2).
 */
class RestaurantService
{
    public function __construct(private readonly FolioService $folio) {}

    // ------------------------------------------------------------------
    // Room bill
    // ------------------------------------------------------------------

    /**
     * Charge menu items to a guest's room bill. All or nothing: if one line fails, none is added.
     *
     * @param  list<array{menu_item_id: int|string, quantity: int|string}>  $lines
     * @return Collection<int, FolioCharge>
     *
     * @throws BookingException
     */
    public function orderToRoom(Booking $booking, array $lines, User $by): Collection
    {
        return DB::transaction(function () use ($booking, $lines, $by): Collection {
            return collect($this->resolve($lines))->map(
                fn (array $line): FolioCharge => $this->folio->addCharge($booking, $this->chargeData($line['item'], $line['quantity']), $by),
            );
        });
    }

    // ------------------------------------------------------------------
    // Walk-in bill
    // ------------------------------------------------------------------

    /**
     * Add items to a walk-in bill, opening a new bill when none is given.
     *
     * @param  list<array{menu_item_id: int|string, quantity: int|string}>  $lines
     *
     * @throws BookingException
     */
    public function addToWalkIn(?WalkInBill $bill, ?string $customer, array $lines, User $by): WalkInBill
    {
        return DB::transaction(function () use ($bill, $customer, $lines, $by): WalkInBill {
            $resolved = $this->resolve($lines);

            if ($bill === null) {
                $name = trim((string) $customer);
                $bill = WalkInBill::create([
                    'customer_name' => $name !== '' ? $name : null,
                    'status' => WalkInStatus::Open,
                    'total' => 0,
                    'created_by' => $by->id,
                ]);
            }

            $bill = $this->lockOpen($bill->id);

            foreach ($resolved as $line) {
                $bill->lines()->create([
                    'menu_item_id' => $line['item']->id,
                    'category' => $line['item']->category,
                    'description' => $line['item']->name,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['item']->price,
                    'amount' => round($line['quantity'] * (float) $line['item']->price, 2),
                    'created_by' => $by->id,
                ]);
            }

            return $this->recalculate($bill);
        });
    }

    /** @throws BookingException */
    public function removeLine(WalkInBillLine $line): WalkInBill
    {
        return DB::transaction(function () use ($line): WalkInBill {
            $bill = $this->lockOpen($line->walk_in_bill_id);

            $line->delete();

            return $this->recalculate($bill);
        });
    }

    /**
     * Take payment for the whole bill and close it. Optionally adds VAT on top of the items. Bank transfer, Telebirr and card need a reference number (FR-20).
     *
     * @throws BookingException
     */
    public function pay(WalkInBill $bill, PaymentMethod $method, ?string $reference, User $by, bool $applyVat = false): WalkInBill
    {
        return DB::transaction(function () use ($bill, $method, $reference, $by, $applyVat): WalkInBill {
            $fresh = $this->lockOpen($bill->id);
            $reference = trim((string) $reference);

            if ((float) $fresh->total <= 0) {
                throw new BookingException('The bill is empty. Add an item first.');
            }

            if ($method !== PaymentMethod::Cash && $reference === '') {
                throw new BookingException('Enter the reference number for a '.strtolower($method->label()).' payment.');
            }

            // VAT is added on top of the items when the cashier ticks "Apply VAT" (only if VAT is switched on in the tax settings).
            $vatRate = (bool) config('tax.vat.enabled') ? max(0.0, (float) config('tax.vat.rate')) : 0.0;
            $applyVat = $applyVat && $vatRate > 0;
            $subtotal = (float) $fresh->total;
            $vat = $applyVat ? round($subtotal * $vatRate / 100, 2) : 0.0;

            $fresh->update([
                'total' => round($subtotal + $vat, 2),
                'vat_applied' => $applyVat,
                'vat_rate' => $applyVat ? $vatRate : 0,
                'vat_amount' => $vat,
                'status' => WalkInStatus::Paid,
                'payment_method' => $method,
                'payment_reference' => $reference !== '' ? $reference : null,
                'paid_at' => now(),
                'paid_by' => $by->id,
            ]);

            return $fresh;
        });
    }

    /**
     * Put an open walk-in bill on a guest's room bill instead (the customer turns out to be a guest).
     *
     * @throws BookingException
     */
    public function moveToRoom(WalkInBill $bill, Booking $booking, User $by): WalkInBill
    {
        return DB::transaction(function () use ($bill, $booking, $by): WalkInBill {
            $fresh = $this->lockOpen($bill->id);
            $lines = $fresh->lines()->get();

            if ($lines->isEmpty()) {
                throw new BookingException('The bill is empty. There is nothing to move.');
            }

            foreach ($lines as $line) {
                $this->folio->addCharge($booking, [
                    'category' => $line->category->value,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                ], $by);
            }

            $fresh->update([
                'status' => WalkInStatus::Transferred,
                'booking_id' => $booking->id,
                'transferred_at' => now(),
                'transferred_by' => $by->id,
            ]);

            return $fresh;
        });
    }

    /**
     * Cancel an open bill, with a reason. A paid bill cannot be voided here.
     *
     * @throws BookingException
     */
    public function void(WalkInBill $bill, string $reason, User $by): WalkInBill
    {
        return DB::transaction(function () use ($bill, $reason, $by): WalkInBill {
            $fresh = WalkInBill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status === WalkInStatus::Paid) {
                throw new BookingException('A paid bill cannot be voided here.');
            }

            if (! $fresh->isOpen()) {
                throw new BookingException('Only an open bill can be voided.');
            }

            $reason = trim($reason);

            if ($reason === '') {
                throw new BookingException('Enter the reason for voiding this bill.');
            }

            $fresh->update([
                'status' => WalkInStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => $reason,
            ]);

            return $fresh;
        });
    }

    // ------------------------------------------------------------------

    /**
     * Match the order lines to items that are on the menu now. The same item twice is added up.
     *
     * @param  list<array{menu_item_id: int|string, quantity: int|string}>  $lines
     * @return list<array{item: MenuItem, quantity: int}>
     *
     * @throws BookingException
     */
    private function resolve(array $lines): array
    {
        $wanted = [];

        foreach ($lines as $line) {
            $id = (int) ($line['menu_item_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($id < 1 || $quantity < 1) {
                throw new BookingException('Every item needs a quantity of at least 1.');
            }

            $wanted[$id] = ($wanted[$id] ?? 0) + $quantity;
        }

        if ($wanted === []) {
            throw new BookingException('Add at least one item.');
        }

        $items = MenuItem::query()->sellable()->whereKey(array_keys($wanted))->get()->keyBy('id');
        $resolved = [];

        foreach ($wanted as $id => $quantity) {
            $item = $items->get($id);

            if ($item === null) {
                throw new BookingException('An item on the order is no longer on the menu. Reload the page and try again.');
            }

            if ($quantity > 99) {
                throw new BookingException("Quantity of {$item->name} cannot be more than 99.");
            }

            $resolved[] = ['item' => $item, 'quantity' => $quantity];
        }

        return $resolved;
    }

    /** @return array{category: string, description: string, quantity: int, unit_price: float} */
    private function chargeData(MenuItem $item, int $quantity): array
    {
        return [
            'category' => $item->category->value,
            'description' => $item->name,
            'quantity' => $quantity,
            'unit_price' => (float) $item->price,
        ];
    }

    /** @throws BookingException */
    private function lockOpen(int $billId): WalkInBill
    {
        $bill = WalkInBill::query()->whereKey($billId)->lockForUpdate()->firstOrFail();

        if (! $bill->isOpen()) {
            throw new BookingException('This bill is already closed ('.strtolower($bill->status->label()).').');
        }

        return $bill;
    }

    private function recalculate(WalkInBill $bill): WalkInBill
    {
        $bill->update(['total' => round((float) $bill->lines()->sum('amount'), 2)]);

        return $bill;
    }
}
