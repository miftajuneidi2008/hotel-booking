<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomTypeRequest;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoomTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.room-types.index', [
            'roomTypes' => RoomType::query()->withCount('rooms')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.room-types.create', ['roomType' => new RoomType]);
    }

    public function store(RoomTypeRequest $request): RedirectResponse
    {
        RoomType::create($request->validated());

        return redirect()->route('admin.room-types.index')->with('status', 'Room type created.');
    }

    public function edit(RoomType $roomType): View
    {
        return view('admin.room-types.edit', ['roomType' => $roomType]);
    }

    public function update(RoomTypeRequest $request, RoomType $roomType): RedirectResponse
    {
        $roomType->update($request->validated());

        return redirect()->route('admin.room-types.index')->with('status', 'Room type updated.');
    }

    public function destroy(RoomType $roomType): RedirectResponse
    {
        if ($roomType->rooms()->exists()) {
            return back()->with('error', "{$roomType->name} still has rooms. Move or delete them first.");
        }

        $roomType->delete();

        return redirect()->route('admin.room-types.index')->with('status', 'Room type deleted.');
    }
}
