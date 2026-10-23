<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomListingController extends Controller
{
    /**
     * Public list of rooms, priced for tonight and filterable by room type.
     */
    public function index(Request $request): View
    {
        $tonight = today();

        $rooms = $this->visibleRooms()
            ->with(['roomType.rates', 'photos' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->orderBy('floor')
            ->orderBy('number')
            ->get();

        $allTypes = RoomType::query()->orderBy('name')->get();
        $counts = $rooms->countBy('room_type_id');

        $slug = $request->string('type')->toString() ?: null;
        $selectedType = $slug ? $allTypes->firstWhere('slug', $slug) : null;
        $typeNotFound = $slug !== null && $selectedType === null;

        $listed = match (true) {
            $typeNotFound => collect(),
            $selectedType !== null => $rooms->where('room_type_id', $selectedType->id),
            default => $rooms,
        };

        return view('rooms', [
            'rooms' => $listed->values()->map(fn (Room $room): array => [
                'slug' => $room->roomType->slug,
                'name' => $room->roomType->name,
                'description' => trim("Room {$room->number}, floor {$room->floor}. Sleeps {$room->roomType->capacity}. {$room->roomType->description}"),
                'price' => $room->roomType->rateForDate($tonight),
                'image' => $room->photos->isNotEmpty() ? 'storage/'.$room->photos->first()->path : null,
                'tag' => null,
                'href' => route('rooms.show', $room),
            ]),
            'navTypes' => $allTypes->filter(fn (RoomType $type): bool => $counts->has($type->id))->values(),
            'counts' => $counts,
            'totalCount' => $rooms->count(),
            'selectedType' => $selectedType,
            'typeNotFound' => $typeNotFound,
        ]);
    }

    public function show(Room $room): View
    {
        abort_if($room->status === RoomStatus::OutOfService, 404);

        $room->load(['roomType.rates', 'photos' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);

        $tonight = today();

        return view('room', [
            'room' => $room,
            'tonightRate' => $room->roomType->rateForDate($tonight),
            'otherRates' => $room->roomType->rates
                ->filter(fn (RoomRate $rate): bool => $rate->ends_on === null || $rate->ends_on->toDateString() >= $tonight->toDateString())
                ->sortBy('name')
                ->values(),
        ]);
    }

    /**
     * Rooms guests may see: everything except rooms that are out of service.
     *
     * @return Builder<Room>
     */
    private function visibleRooms(): Builder
    {
        return Room::query()->where('status', '!=', RoomStatus::OutOfService->value);
    }
}
