<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Enums\IssuePriority;
use App\Enums\RoomStatus;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeRoomStatusRequest;
use App\Models\Booking;
use App\Models\MaintenanceIssue;
use App\Models\Room;
use App\Models\RoomStatusChange;
use App\Services\HousekeepingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Housekeeping board and room page (FR-24, FR-25). Built for a phone or tablet. */
class HousekeepingController extends Controller
{
    public function __construct(private readonly HousekeepingService $housekeeping) {}

    public function index(): View
    {
        Gate::authorize('viewAny', RoomStatusChange::class);

        $rooms = Room::query()
            ->with('roomType')
            ->withCount(['bookings as arrivals_today' => fn ($q) => $q
                ->where('status', BookingStatus::Confirmed->value)
                ->whereDate('check_in', '<=', today()->toDateString())
                ->whereDate('check_out', '>', today()->toDateString())])
            ->orderBy('floor')
            ->orderBy('number')
            ->get();

        $openIssues = MaintenanceIssue::query()->open()->get()->groupBy('room_id');

        return view('staff.housekeeping.board', [
            'floors' => $rooms->groupBy('floor'),
            'counts' => [
                'all' => $rooms->count(),
                RoomStatus::Dirty->value => $rooms->where('status', RoomStatus::Dirty)->count(),
                RoomStatus::Clean->value => $rooms->where('status', RoomStatus::Clean)->count(),
                RoomStatus::Occupied->value => $rooms->where('status', RoomStatus::Occupied)->count(),
                RoomStatus::OutOfService->value => $rooms->where('status', RoomStatus::OutOfService)->count(),
            ],
            'openIssues' => $openIssues,
        ]);
    }

    public function show(Room $room): View
    {
        Gate::authorize('viewAny', RoomStatusChange::class);

        $room->load('roomType');

        return view('staff.housekeeping.show', [
            'room' => $room,
            'openIssues' => MaintenanceIssue::query()->where('room_id', $room->id)->open()->with('reporter')->latest('id')->get(),
            'resolvedIssues' => MaintenanceIssue::query()->where('room_id', $room->id)->whereNotNull('resolved_at')->with(['reporter', 'resolver'])->latest('resolved_at')->limit(5)->get(),
            'history' => RoomStatusChange::query()->where('room_id', $room->id)->with('changer')->latest('id')->limit(15)->get(),
            'upcoming' => $this->housekeeping->upcomingBookings($room),
            'guestInHouse' => Booking::query()->where('room_id', $room->id)->where('status', BookingStatus::CheckedIn->value)->exists(),
            'priorities' => IssuePriority::cases(),
        ]);
    }

    public function status(ChangeRoomStatusRequest $request, Room $room): RedirectResponse
    {
        try {
            $this->housekeeping->changeStatus(
                $room,
                RoomStatus::from($request->validated('status')),
                $request->user(),
                $request->validated('note'),
            );
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Room {$room->number} is now ".strtolower(RoomStatus::from($request->validated('status'))->label()).'.');
    }
}
