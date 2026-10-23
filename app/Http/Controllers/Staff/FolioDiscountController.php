<?php

namespace App\Http\Controllers\Staff;

use App\Enums\DiscountType;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFolioDiscountRequest;
use App\Http\Requests\VoidFolioEntryRequest;
use App\Models\Booking;
use App\Models\FolioDiscount;
use App\Services\FolioService;
use Illuminate\Http\RedirectResponse;

/** Discounts on a guest bill with a reason and an approval rule (FR-17). */
class FolioDiscountController extends Controller
{
    public function __construct(private readonly FolioService $folio) {}

    public function store(StoreFolioDiscountRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $discount = $this->folio->applyDiscount(
                $booking,
                DiscountType::from($request->validated('type')),
                (float) $request->validated('value'),
                $request->validated('reason'),
                $request->user(),
            );
        } catch (BookingException $e) {
            return redirect()->route('staff.folio.show', $booking)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.folio.show', $booking)
            ->with('status', 'Discount of ETB '.number_format((float) $discount->amount, 2).' applied.');
    }

    public function void(VoidFolioEntryRequest $request, FolioDiscount $discount): RedirectResponse
    {
        try {
            $this->folio->voidDiscount($discount, $request->validated('reason'), $request->user());
        } catch (BookingException $e) {
            return redirect()->route('staff.folio.show', $discount->booking_id)->with('error', $e->getMessage());
        }

        return redirect()->route('staff.folio.show', $discount->booking_id)->with('status', 'The discount is removed.');
    }
}
