<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingSource;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\GroupBookingRequest;
use App\Models\BookingGroup;
use App\Services\BookingService;
use App\Services\GroupBookingService;
use App\Support\StayRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Group and company bookings with several rooms (FR-10).
 */
class GroupBookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly GroupBookingService $groups,
    ) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('staff.groups.index', [
            'term' => $term,
            'groups' => BookingGroup::query()
                ->withCount('bookings')
                ->withMin('bookings', 'check_in')
                ->withMax('bookings', 'check_out')
                ->when($term !== '', function ($query) use ($term): void {
                    $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
                    $query->where(fn ($q) => $q->where('name', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhere('contact_phone', 'like', $like));
                })
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    /**
     * Step 1 asks for the dates. Step 2 lists every free room to tick, plus the group's details.
     */
    public function create(Request $request): View
    {
        $search = null;
        $rooms = null;
        $quotes = [];

        if ($request->hasAny(['checkin', 'checkout'])) {
            $validated = $request->validate(Arr::only(StayRules::rules(), ['checkin', 'checkout']));

            $checkIn = date('Y-m-d', strtotime((string) $validated['checkin']));
            $checkOut = date('Y-m-d', strtotime((string) $validated['checkout']));

            // Guests per room are chosen below, so list every room that is free (capacity filter = 1).
            $rooms = $this->bookings->availableRooms($checkIn, $checkOut, 1);

            foreach ($rooms->pluck('roomType')->unique('id') as $type) {
                $quotes[$type->id] = $this->bookings->quote($type, $checkIn, $checkOut);
            }

            $search = ['checkin' => $checkIn, 'checkout' => $checkOut];
        }

        return view('staff.groups.create', [
            'search' => $search,
            'rooms' => $rooms,
            'quotes' => $quotes,
            'sources' => BookingSource::desk(),
        ]);
    }

    public function store(GroupBookingRequest $request): RedirectResponse
    {
        $input = $request->validated();

        $checkIn = date('Y-m-d', strtotime((string) $input['checkin']));
        $checkOut = date('Y-m-d', strtotime((string) $input['checkout']));

        try {
            $group = $this->groups->create(
                roomIds: $input['room_ids'],
                guestsPerRoom: $input['guests'] ?? [],
                data: [
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'name' => $input['name'],
                    'contact_name' => $input['contact_name'] ?? null,
                    'contact_phone' => $input['contact_phone'],
                    'contact_email' => $input['contact_email'] ?? null,
                    'notes' => $input['notes'] ?? null,
                    'source' => BookingSource::from($input['source']),
                ],
                staff: $request->user(),
            );
        } catch (BookingException $e) {
            return redirect()
                ->route('staff.groups.create', ['checkin' => $checkIn, 'checkout' => $checkOut])
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('staff.groups.show', $group)
            ->with('status', "Group {$group->reference} saved with {$group->bookings->count()} rooms.");
    }

    public function show(BookingGroup $group): View
    {
        return view('staff.groups.show', [
            'group' => $group->load(['bookings' => fn ($q) => $q->with('room.roomType')->orderBy('id'), 'creator']),
        ]);
    }

    public function cancel(CancelBookingRequest $request, BookingGroup $group): RedirectResponse
    {
        try {
            $count = $this->groups->cancel($group, $request->validated('reason'), $request->user());
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('staff.groups.show', $group)
            ->with('status', "{$count} ".str('room')->plural($count)." cancelled for group {$group->reference}.");
    }
}
