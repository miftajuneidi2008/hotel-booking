<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomRateRequest;
use App\Models\RoomRate;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoomRateController extends Controller
{
    public function index(RoomType $roomType): View
    {
        return view('admin.room-types.rates', [
            'roomType' => $roomType,
            'rates' => $roomType->rates()->orderBy('name')->get(),
        ]);
    }

    public function store(RoomRateRequest $request, RoomType $roomType): RedirectResponse
    {
        $roomType->rates()->create($request->validated());

        return redirect()->route('admin.room-types.rates.index', $roomType)->with('status', 'Rate added.');
    }

    public function destroy(RoomType $roomType, RoomRate $rate): RedirectResponse
    {
        $rate->delete();

        return redirect()->route('admin.room-types.rates.index', $roomType)->with('status', 'Rate deleted.');
    }
}
