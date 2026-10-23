<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Exceptions\BookingException;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\ReviewBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingService;
use App\Support\StayRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Guest self-service: search, book, and manage your own bookings on the website.
 */
class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    /**
     * Search page. With dates it lists the room types that still have a free room.
     */
    public function index(Request $request): View
    {
        $search = null;
        $results = null;
        $preferred = $request->query('room'); // room type slug, set by "Book this room"

        // Only validate when the user actually submitted the search form
        if ($request->hasAny(['checkin', 'checkout', 'guests'])) {
            $search = $request->validate(StayRules::rules());

            [$checkIn, $checkOut, $guests] = StayRules::normalise($search);

            $results = $this->bookings->availableTypes($checkIn, $checkOut, $guests)
                ->sortBy(fn (array $row): array => [$row['type']->slug === $preferred ? 0 : 1, $row['quote']['total']])
                ->values();

            $search = ['checkin' => $checkIn, 'checkout' => $checkOut, 'guests' => $guests];
        }

        return view('booking', [
            'search' => $search,
            'results' => $results,
            'preferred' => $preferred,
            'roomTypes' => $search ? collect() : $this->roomTypeCards(),
        ]);
    }

    /**
     * Step 2: confirm the choice and enter guest details. Needs an account.
     */
    public function review(ReviewBookingRequest $request): View|RedirectResponse
    {
        $input = $request->validated();
        [$checkIn, $checkOut, $guests] = StayRules::normalise($input);

        $type = RoomType::query()->where('slug', $input['type'])->firstOrFail();

        $free = $this->bookings->availableRooms($checkIn, $checkOut, $guests, $type->id);

        if ($free->isEmpty()) {
            return $this->backToSearch($checkIn, $checkOut, $guests, $type->slug)
                ->with('error', __('Sorry, that room type is no longer available for those dates.'));
        }

        return view('bookings.review', [
            'type' => $type,
            'quote' => $this->bookings->quote($type, $checkIn, $checkOut),
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'guests' => $guests,
            'user' => $request->user(),
        ]);
    }

    /**
     * Step 3: save the booking. The system assigns a free room of the chosen type.
     */
    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $input = $request->validated();
        [$checkIn, $checkOut, $guests] = StayRules::normalise($input);

        $type = RoomType::query()->where('slug', $input['type'])->firstOrFail();

        try {
            $booking = $this->bookings->bookRoomType($type, [
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guests' => $guests,
                'guest_name' => $input['guest_name'],
                'guest_phone' => $input['guest_phone'],
                'guest_email' => $input['guest_email'] ?? null,
                'notes' => $input['notes'] ?? null,
            ], $request->user());
        } catch (BookingException $e) {
            return $this->backToSearch($checkIn, $checkOut, $guests, $type->slug)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('my-bookings.show', $booking)
            ->with('status', __('Your booking is confirmed. Please keep your reference: :reference', ['reference' => $booking->reference]));
    }

    public function mine(Request $request): View
    {
        return view('bookings.index', [
            'bookings' => $request->user()->bookings()
                ->with('room.roomType')
                ->latest('check_in')
                ->latest('id')
                ->paginate(10),
        ]);
    }

    public function show(Booking $booking): View
    {
        Gate::authorize('view', $booking);

        return view('bookings.show', ['booking' => $booking->load('room.roomType')]);
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
            ->route('my-bookings.show', $booking)
            ->with('status', __('Your booking has been cancelled.'));
    }

    private function backToSearch(string $checkIn, string $checkOut, int $guests, string $slug): RedirectResponse
    {
        return redirect()->route('booking', [
            'checkin' => $checkIn,
            'checkout' => $checkOut,
            'guests' => $guests,
            'room' => $slug,
        ]);
    }

    /**
     * Room types to show on the search page before any dates are chosen.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function roomTypeCards(): Collection
    {
        $today = today();

        return RoomType::query()
            ->whereHas('rooms', fn ($rooms) => $rooms->where('status', '!=', RoomStatus::OutOfService->value))
            ->with(['rates', 'rooms.photos' => fn ($photos) => $photos->orderBy('sort_order')->orderBy('id')])
            ->orderBy('name')
            ->get()
            ->map(function (RoomType $type) use ($today): array {
                /** @var Room|null $withPhoto */
                $withPhoto = $type->rooms->first(fn (Room $room): bool => $room->photos->isNotEmpty());

                return [
                    'slug' => $type->slug,
                    'name' => $type->name,
                    'description' => (string) $type->description,
                    'price' => $type->rateForDate($today),
                    'image' => $withPhoto ? 'storage/'.$withPhoto->photos->first()->path : null,
                    'tag' => null,
                ];
            });
    }
}
