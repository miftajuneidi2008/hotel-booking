<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFolioChargeRequest;
use App\Http\Requests\TransferFolioChargeRequest;
use App\Http\Requests\VoidFolioEntryRequest;
use App\Models\Booking;
use App\Models\FolioCharge;
use App\Services\FolioService;
use Illuminate\Http\RedirectResponse;

/** Extra charges on a guest bill: add (FR-16), void, and move or split (FR-18). */
class FolioChargeController extends Controller
{
    public function __construct(private readonly FolioService $folio) {}

    public function store(StoreFolioChargeRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $charge = $this->folio->addCharge($booking, $request->validated(), $request->user());
        } catch (BookingException $e) {
            return redirect()->route('staff.folio.show', $booking)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.folio.show', $booking)
            ->with('status', "Added {$charge->description}: ETB ".number_format((float) $charge->amount, 2).'.');
    }

    public function void(VoidFolioEntryRequest $request, FolioCharge $charge): RedirectResponse
    {
        try {
            $this->folio->voidCharge($charge, $request->validated('reason'), $request->user());
        } catch (BookingException $e) {
            return redirect()->route('staff.folio.show', $charge->booking_id)->with('error', $e->getMessage());
        }

        return redirect()->route('staff.folio.show', $charge->booking_id)->with('status', 'The charge is voided.');
    }

    public function transfer(TransferFolioChargeRequest $request, FolioCharge $charge): RedirectResponse
    {
        $from = $charge->booking_id;

        try {
            $moved = $this->folio->transferCharge(
                $charge,
                Booking::query()->findOrFail($request->validated('to_booking_id')),
                (float) $request->validated('amount'),
                $request->validated('reason'),
                $request->user(),
            );
        } catch (BookingException $e) {
            return redirect()->route('staff.folio.show', $from)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.folio.show', $from)
            ->with('status', 'ETB '.number_format((float) $moved->amount, 2).' moved to '.$moved->booking->guest_name.'.');
    }
}
