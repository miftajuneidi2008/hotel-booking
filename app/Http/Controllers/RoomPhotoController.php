<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomPhotoRequest;
use App\Models\Room;
use App\Models\RoomPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class RoomPhotoController extends Controller
{
    public function store(RoomPhotoRequest $request, Room $room): RedirectResponse
    {
        $sortOrder = (int) $room->photos()->max('sort_order');

        foreach ($request->file('photos') as $file) {
            $room->photos()->create([
                'path' => $file->store("rooms/{$room->id}", 'public'),
                'sort_order' => ++$sortOrder,
            ]);
        }

        return redirect()->route('admin.rooms.edit', $room)->with('status', 'Photos uploaded.');
    }

    public function destroy(Room $room, RoomPhoto $photo): RedirectResponse
    {
        Storage::disk('public')->delete($photo->path);

        $photo->delete();

        return redirect()->route('admin.rooms.edit', $room)->with('status', 'Photo deleted.');
    }
}
