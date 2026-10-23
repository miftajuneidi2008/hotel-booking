<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Enums\ChargeCategory;
use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\FolioCharge;
use App\Models\Invoice;
use App\Services\FolioService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The guest bill (folio): view it, work on it, and print it (FR-15, FR-19).
 */
class FolioController extends Controller
{
    public function __construct(private readonly FolioService $folio) {}

    /** Guests who are in the house, with what they owe. Also the starting point for the cashier role. */
    public function index(): View
    {
        Gate::authorize('viewAny', FolioCharge::class);

        $bookings = Booking::query()
            ->with('room.roomType')
            ->where('status', BookingStatus::CheckedIn->value)
            ->get()
            ->sortBy(fn (Booking $b): string => str_pad($b->room->number, 6, '0', STR_PAD_LEFT))
            ->values();

        return view('staff.folio.index', ['bookings' => $bookings]);
    }

    public function show(Booking $booking): View
    {
        Gate::authorize('viewAny', FolioCharge::class);

        $booking->load('room.roomType');

        return view('staff.folio.show', [
            'booking' => $booking,
            'folio' => $this->folio->summary($booking),
            'categories' => ChargeCategory::cases(),
            'discountTypes' => DiscountType::cases(),
            'targets' => $booking->folioAcceptsCharges() ? $this->folio->transferTargets($booking) : collect(),
            'limits' => config('folio'),
        ]);
    }

    /** 80 mm receipt for a thermal printer. */
    public function receipt(Booking $booking): View|RedirectResponse
    {
        return $this->printable($booking, 'staff.folio.receipt');
    }

    /**
     * A4 invoice (section 5.9). Opens the issued invoice when there is one; otherwise shows a draft of the
     * bill that can be issued after check-out.
     */
    public function invoice(Booking $booking, InvoiceService $invoices): View|RedirectResponse
    {
        Gate::authorize('viewAny', FolioCharge::class);

        if ($booking->status === BookingStatus::Cancelled) {
            return redirect()->route('staff.folio.show', $booking)->with('error', 'A cancelled booking has no bill to print.');
        }

        if (($issued = $invoices->activeFor($booking)) !== null) {
            return redirect()->route('staff.invoices.show', $issued);
        }

        $blocker = $invoices->issueBlocker($booking);

        return view('staff.folio.invoice', [
            'doc' => $invoices->draft($booking),
            'invoice' => null,
            'bookingId' => $booking->id,
            'blocker' => $blocker,
            'canIssue' => $blocker === null && Gate::allows('create', Invoice::class),
            'printedBy' => request()->user()->name,
        ]);
    }

    private function printable(Booking $booking, string $view): View|RedirectResponse
    {
        Gate::authorize('viewAny', FolioCharge::class);

        if ($booking->status === BookingStatus::Cancelled) {
            return redirect()->route('staff.folio.show', $booking)->with('error', 'A cancelled booking has no bill to print.');
        }

        return view($view, [
            'booking' => $booking->load(['room.roomType', 'roomMoves.fromRoom', 'roomMoves.toRoom']),
            'folio' => $this->folio->summary($booking),
            'property' => config('hotel.name', config('app.name')),
            'printedBy' => request()->user()->name,
        ]);
    }
}
