<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Http\Requests\RoomRequest;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'room_type_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(RoomStatus::class)],
        ]);

        $rooms = Room::query()
            ->with('roomType')
            ->withCount('photos')
            ->when($filters['room_type_id'] ?? null, fn ($query, $roomTypeId) => $query->where('room_type_id', $roomTypeId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('floor')
            ->orderBy('number')
            ->get();

        return view('admin.rooms.index', [
            'rooms' => $rooms,
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
            'statuses' => RoomStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.rooms.create', [
            'room' => new Room,
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
            'statuses' => RoomStatus::cases(),
        ]);
    }

    public function store(RoomRequest $request): RedirectResponse
    {
        $room = Room::create($request->validated());

        return redirect()->route('admin.rooms.edit', $room)->with('status', 'Room created. You can now add photos.');
    }

    public function edit(Room $room): View
    {
        return view('admin.rooms.edit', [
            'room' => $room->load(['photos' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]),
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
            'statuses' => RoomStatus::cases(),
        ]);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $room->update($request->validated());

        return redirect()->route('admin.rooms.index')->with('status', 'Room updated.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        if ($room->status === RoomStatus::Occupied) {
            return back()->with('error', "Room {$room->number} is occupied. Check the guest out before removing it.");
        }

        $room->delete();

        return redirect()->route('admin.rooms.index')->with('status', 'Room deleted.');
    }
}
