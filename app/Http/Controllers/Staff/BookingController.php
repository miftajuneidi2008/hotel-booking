<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\StaffBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use App\Support\StayRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Front desk: book rooms for guests who walk in or phone, and manage all bookings.
 * Routes are limited to desk staff by the "manage-bookings" gate.
 */
class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_column(BookingStatus::cases(), 'value'))],
            'date' => ['nullable', 'date'],
        ]);

        $bookings = Booking::query()
            ->with('room.roomType')
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($query) use ($term): void {
                    $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

                    $query->where('reference', 'like', $like)
                        ->orWhere('guest_name', 'like', $like)
                        ->orWhere('guest_phone', 'like', $like);
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['date'] ?? null, function ($query, string $date): void {
                $day = date('Y-m-d', strtotime($date));
                $query->overlapping($day, date('Y-m-d', strtotime($day.' +1 day')));
            })
            ->latest('check_in')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('staff.bookings.index', [
            'bookings' => $bookings,
            'filters' => array_filter($filters),
            'statuses' => BookingStatus::cases(),
        ]);
    }

    /**
     * Step 1 asks for dates and guests. Step 2 (after the search) lists free rooms and the guest form.
     */
    public function create(Request $request): View
    {
        $search = null;
        $rooms = null;
        $quotes = [];

        if ($request->hasAny(['checkin', 'checkout', 'guests'])) {
            $validated = $request->validate(StayRules::rules());
            [$checkIn, $checkOut, $guests] = StayRules::normalise($validated);

            $rooms = $this->bookings->availableRooms($checkIn, $checkOut, $guests);

            foreach ($rooms->pluck('roomType')->unique('id') as $type) {
                $quotes[$type->id] = $this->bookings->quote($type, $checkIn, $checkOut);
            }

            $search = ['checkin' => $checkIn, 'checkout' => $checkOut, 'guests' => $guests];
        }

        return view('staff.bookings.create', [
            'search' => $search,
            'rooms' => $rooms,
            'quotes' => $quotes,
            'sources' => BookingSource::desk(),
            'methods' => PaymentMethod::cases(),
            'isToday' => $search !== null && $search['checkin'] === today()->toDateString(),
        ]);
    }

    public function store(StaffBookingRequest $request): RedirectResponse
    {
        $input = $request->validated();
        [$checkIn, $checkOut, $guests] = StayRules::normalise($input);

        $room = Room::query()->findOrFail($input['room_id']);

        try {
            $booking = $this->bookings->bookRoom($room, [
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guests' => $guests,
                'source' => BookingSource::from($input['source']),
                'guest_name' => $input['guest_name'],
                'guest_phone' => $input['guest_phone'],
                'guest_email' => $input['guest_email'] ?? null,
                'notes' => $input['notes'] ?? null,
                'deposit_amount' => $input['deposit_amount'] ?? 0,
                'deposit_method' => $input['deposit_method'] ?? null,
                'deposit_reference' => $input['deposit_reference'] ?? null,
                'check_in_now' => $request->boolean('check_in_now'),
            ], $request->user());
        } catch (BookingException $e) {
            // Keep what the receptionist typed; send them back to the room list for the same dates.
            return redirect()
                ->route('staff.bookings.create', ['checkin' => $checkIn, 'checkout' => $checkOut, 'guests' => $guests])
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('staff.bookings.show', $booking)
            ->with('status', "Booking {$booking->reference} saved for room {$booking->room->number}.");
    }

    public function show(Booking $booking): View
    {
        return view('staff.bookings.show', [
            'booking' => $booking->load(['room.roomType', 'user', 'creator', 'canceller', 'checkedInBy', 'checkedOutBy', 'checkoutApprovedBy', 'roomMoves.fromRoom', 'roomMoves.toRoom']),
        ]);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        try {
            $this->bookings->cancel($booking, $request->validated('reason'), $request->user());
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('staff.bookings.show', $booking)
            ->with('status', "Booking {$booking->reference} cancelled.");
    }
}
