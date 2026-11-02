<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Enums\ChargeCategory;
use App\Enums\UserRole;
use App\Enums\WalkInStatus;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\Booking;
use App\Models\MenuItem;
use App\Models\RoomTableHold;
use App\Models\WalkInBill;
use App\Services\RestaurantOrderAlertService;
use App\Services\RestaurantService;
use App\Services\RoomChargeAlertService;
use App\Services\TableOccupancyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Take an order from the menu and put it on a room bill or a walk-in bill (FR-28). */
class OrderController extends Controller
{
    public function __construct(
        private readonly RestaurantService $restaurant,
        private readonly RoomChargeAlertService $alerts,
        private readonly RestaurantOrderAlertService $orderAlerts,
        private readonly TableOccupancyService $tables,
    ) {}

    public function create(Request $request): View
    {
        Gate::authorize('viewAny', WalkInBill::class);

        $items = MenuItem::query()->sellable()->orderBy('name')->get()
            ->map(fn (MenuItem $i): array => [
                'id' => $i->id,
                'name' => $i->name,
                'price' => (float) $i->price,
                'category' => $i->category->value,
            ])->values();

        $guests = Booking::query()
            ->with('room')
            ->where('status', BookingStatus::CheckedIn->value)
            ->get()
            ->sortBy(fn (Booking $b): string => str_pad($b->room->number, 6, '0', STR_PAD_LEFT))
            ->map(fn (Booking $b): array => ['id' => $b->id, 'label' => __('Room').' '.$b->room->number.' · '.$b->guest_name.' · '.__('unpaid').' ETB '.number_format(max($b->balanceDue(), 0), 2)])
            ->values();

        // The receptionist orders for guests in a room only, so there are no walk-in bills to offer.
        $canWalkIn = $request->user()->canOrderForWalkIn();

        $openBills = ! $canWalkIn ? collect() : WalkInBill::query()->where('status', WalkInStatus::Open->value)->latest('id')->get()
            ->map(fn (WalkInBill $b): array => [
                'id' => $b->id,
                'label' => $b->number.($b->customer_name ? ' · '.$b->customer_name : '').($b->table_number ? ' · '.__('Table').' '.$b->table_number : '').' · ETB '.number_format((float) $b->total, 2),
            ])->values();

        $billId = (int) $request->query('bill');
        $bookingId = (int) $request->query('booking');

        return view('staff.orders.create', [
            'items' => $items,
            'categories' => collect(ChargeCategory::cases())->map(fn (ChargeCategory $c): array => ['value' => $c->value, 'label' => $c->label()])->values(),
            'guests' => $guests,
            'openBills' => $openBills,
            'startTarget' => $billId > 0 && $openBills->contains('id', $billId) ? 'walk_in' : 'room',
            'startBill' => $openBills->contains('id', $billId) ? $billId : null,
            'startBooking' => $guests->contains('id', $bookingId) ? $bookingId : null,
            'canEditMenu' => $request->user()->can('create', MenuItem::class),
            'canWalkIn' => $canWalkIn,
            'requireTable' => $request->user()->role === UserRole::Receptionist,
            'occupiedTables' => $this->tables->occupied(),
        ]);
    }

    /** Free a table that a hotel guest was using (the guest has left the table). */
    public function releaseTable(Request $request, RoomTableHold $hold): RedirectResponse
    {
        Gate::authorize('viewAny', WalkInBill::class);

        $this->tables->release($hold, $request->user());

        return redirect()->route('staff.orders.create')
            ->with('status', __('Table :table is free again.', ['table' => $hold->table_number]));
    }

    public function store(OrderRequest $request): RedirectResponse
    {
        $lines = $request->validated('items');

        try {
            if ($request->validated('target') === 'room') {
                $booking = Booking::query()->findOrFail($request->validated('booking_id'));
                $table = $request->validated('table_number');
                $charges = $this->restaurant->orderToRoom($booking, $lines, $request->user(), $table);

                // Tell the front desk when this order takes the guest's unpaid balance over the alert limit.
                $this->alerts->roomCharge($booking, (float) $charges->sum('amount'), $request->user());

                // Tell the restaurant cashier about an order the front desk took: guest, room, table and items.
                $this->orderAlerts->roomOrder($booking, $this->tables->normalize($table), $charges, $request->user());

                return redirect()->route('staff.folio.show', $booking)->with('status',
                    $charges->count().' '.str('item')->plural($charges->count()).' added to the room bill: ETB '
                    .number_format((float) $charges->sum('amount'), 2).'.');
            }

            $bill = $request->validated('walk_in_bill_id')
                ? WalkInBill::query()->findOrFail($request->validated('walk_in_bill_id'))
                : null;

            $bill = $this->restaurant->addToWalkIn($bill, $request->validated('customer_name'), $lines, $request->user(), $request->validated('table_number'));
        } catch (BookingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.walk-in-bills.show', $bill)
            ->with('status', "Items added to {$bill->number}.");
    }
}
