<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\WalkInStatus;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MoveWalkInBillRequest;
use App\Http\Requests\PayWalkInBillRequest;
use App\Http\Requests\VoidWalkInBillRequest;
use App\Models\Booking;
use App\Models\WalkInBill;
use App\Models\WalkInBillLine;
use App\Services\RestaurantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Walk-in bills for restaurant and bar customers who are not hotel guests (FR-28). */
class WalkInBillController extends Controller
{
    public function __construct(private readonly RestaurantService $restaurant) {}

    public function index(): View
    {
        Gate::authorize('viewAny', WalkInBill::class);

        $open = WalkInBill::query()->withCount('lines')->where('status', WalkInStatus::Open->value)->latest('id')->get();

        $closedToday = WalkInBill::query()
            ->withCount('lines')
            ->where('status', '!=', WalkInStatus::Open->value)
            ->whereDate('updated_at', today()->toDateString())
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return view('staff.walk-in.index', [
            'open' => $open,
            'closedToday' => $closedToday,
            'paidToday' => round((float) $closedToday->where('status', WalkInStatus::Paid)->sum('total'), 2),
        ]);
    }

    public function show(WalkInBill $bill): View
    {
        Gate::authorize('view', $bill);

        $bill->load(['lines', 'creator', 'payer', 'mover', 'voider', 'booking']);

        return view('staff.walk-in.show', [
            'bill' => $bill,
            'methods' => PaymentMethod::cases(),
            'guests' => $bill->isOpen()
                ? Booking::query()->with('room')->where('status', BookingStatus::CheckedIn->value)->get()
                    ->sortBy(fn (Booking $b): string => str_pad($b->room->number, 6, '0', STR_PAD_LEFT))->values()
                : collect(),
        ]);
    }

    public function removeLine(WalkInBillLine $line): RedirectResponse
    {
        $bill = $line->bill;

        Gate::authorize('update', $bill);

        try {
            $this->restaurant->removeLine($line);
        } catch (BookingException $e) {
            return redirect()->route('staff.walk-in-bills.show', $bill)->with('error', $e->getMessage());
        }

        return redirect()->route('staff.walk-in-bills.show', $bill)->with('status', 'The item is removed.');
    }

    public function pay(PayWalkInBillRequest $request, WalkInBill $bill): RedirectResponse
    {
        try {
            $this->restaurant->pay($bill, PaymentMethod::from($request->validated('method')), $request->validated('reference'), $request->user());
        } catch (BookingException $e) {
            return redirect()->route('staff.walk-in-bills.show', $bill)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.walk-in-bills.show', $bill)->with('status', 'Payment received. The bill is closed.');
    }

    public function move(MoveWalkInBillRequest $request, WalkInBill $bill): RedirectResponse
    {
        try {
            $booking = Booking::query()->findOrFail($request->validated('booking_id'));
            $this->restaurant->moveToRoom($bill, $booking, $request->user());
        } catch (BookingException $e) {
            return redirect()->route('staff.walk-in-bills.show', $bill)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.folio.show', $booking)->with('status', "{$bill->number} is now on {$booking->guest_name}'s room bill.");
    }

    public function void(VoidWalkInBillRequest $request, WalkInBill $bill): RedirectResponse
    {
        try {
            $this->restaurant->void($bill, $request->validated('reason'), $request->user());
        } catch (BookingException $e) {
            return redirect()->route('staff.walk-in-bills.show', $bill)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.walk-in-bills.index')->with('status', "{$bill->number} is voided.");
    }

    /** 80 mm receipt for a thermal printer. */
    public function receipt(WalkInBill $bill): View|RedirectResponse
    {
        Gate::authorize('view', $bill);

        if ($bill->status === WalkInStatus::Voided) {
            return redirect()->route('staff.walk-in-bills.show', $bill)->with('error', 'A voided bill has no receipt.');
        }

        return view('staff.walk-in.receipt', [
            'bill' => $bill->load(['lines', 'booking']),
            'property' => config('hotel.name', config('app.name')),
            'printedBy' => request()->user()->name,
        ]);
    }
}
