<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomListingController;
use App\Http\Controllers\RoomPhotoController;
use App\Http\Controllers\RoomRateController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\Staff\BookingChangeController;
use App\Http\Controllers\Staff\BookingController as StaffBookingController;
use App\Http\Controllers\Staff\CalendarController;
use App\Http\Controllers\Staff\FrontDeskController;
use App\Http\Controllers\Staff\GroupBookingController;
use App\Http\Controllers\Staff\InHouseController;
use App\Http\Controllers\Staff\StayController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Guest booking: searching is public, booking and managing bookings need an account.
Route::get('/booking', [BookingController::class, 'index'])->name('booking');
Route::get('/rooms', [RoomListingController::class, 'index'])->name('rooms');
Route::get('/rooms/{room}', [RoomListingController::class, 'show'])->name('rooms.show');

Route::middleware('auth')->group(function () {
    Route::get('/booking/review', [BookingController::class, 'review'])->name('booking.review');
    Route::post('/booking', [BookingController::class, 'store'])->name('booking.store');

    Route::get('/my-bookings', [BookingController::class, 'mine'])->name('my-bookings.index');
    Route::get('/my-bookings/{booking}', [BookingController::class, 'show'])->name('my-bookings.show');
    Route::patch('/my-bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('my-bookings.cancel');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

});

// Front desk: receptionist, manager and owner book rooms for guests and manage all bookings.
Route::middleware(['auth', 'verified', 'can:manage-bookings'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('bookings', [StaffBookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/create', [StaffBookingController::class, 'create'])->name('bookings.create');
    Route::post('bookings', [StaffBookingController::class, 'store'])->name('bookings.store');
    Route::get('bookings/{booking}', [StaffBookingController::class, 'show'])->name('bookings.show');
    Route::patch('bookings/{booking}/cancel', [StaffBookingController::class, 'cancel'])->name('bookings.cancel');

    // Change a confirmed booking (dates, guests, room, guest details).
    Route::get('bookings/{booking}/edit', [BookingChangeController::class, 'edit'])->name('bookings.edit');
    Route::put('bookings/{booking}', [BookingChangeController::class, 'update'])->name('bookings.update');

    // Today at the front desk, check-in, check-out, in-house changes.
    Route::get('front-desk', [FrontDeskController::class, 'index'])->name('front-desk');

    Route::get('bookings/{booking}/check-in', [StayController::class, 'checkInForm'])->name('bookings.check-in');
    Route::post('bookings/{booking}/check-in', [StayController::class, 'checkIn'])->name('bookings.check-in.store');
    Route::patch('bookings/{booking}/guest-id', [StayController::class, 'saveId'])->name('bookings.guest-id');
    Route::get('bookings/{booking}/check-out', [StayController::class, 'checkOutForm'])->name('bookings.check-out');
    Route::post('bookings/{booking}/check-out', [StayController::class, 'checkOut'])->name('bookings.check-out.store');
    Route::get('bookings/{booking}/bill', [StayController::class, 'bill'])->name('bookings.bill');
    Route::get('bookings/{booking}/move-room', [InHouseController::class, 'moveForm'])->name('bookings.move-room');
    Route::post('bookings/{booking}/move-room', [InHouseController::class, 'move'])->name('bookings.move-room.store');
    Route::get('bookings/{booking}/stay', [InHouseController::class, 'stayForm'])->name('bookings.stay');
    Route::put('bookings/{booking}/stay', [InHouseController::class, 'updateStay'])->name('bookings.stay.update');
    Route::patch('rooms/{room}/clean', [StayController::class, 'markClean'])->name('rooms.clean');

    // Calendar of all rooms by date.
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');

    // Group and company bookings with several rooms.
    Route::get('groups', [GroupBookingController::class, 'index'])->name('groups.index');
    Route::get('groups/create', [GroupBookingController::class, 'create'])->name('groups.create');
    Route::post('groups', [GroupBookingController::class, 'store'])->name('groups.store');
    Route::get('groups/{group}', [GroupBookingController::class, 'show'])->name('groups.show');
    Route::patch('groups/{group}/cancel', [GroupBookingController::class, 'cancel'])->name('groups.cancel');
});

// Rooms, room types and rates: owner and manager only (guests can now register on the website).
Route::middleware(['auth', 'verified', 'can:manage-property'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('room-types', RoomTypeController::class)
        ->except('show')
        ->parameters(['room-types' => 'roomType']);

    Route::get('room-types/{roomType}/rates', [RoomRateController::class, 'index'])->name('room-types.rates.index');
    Route::post('room-types/{roomType}/rates', [RoomRateController::class, 'store'])->name('room-types.rates.store');
    Route::delete('room-types/{roomType}/rates/{rate}', [RoomRateController::class, 'destroy'])
        ->scopeBindings()
        ->name('room-types.rates.destroy');

    Route::resource('rooms', RoomController::class)->except('show');

    Route::post('rooms/{room}/photos', [RoomPhotoController::class, 'store'])->name('rooms.photos.store');
    Route::delete('rooms/{room}/photos/{photo}', [RoomPhotoController::class, 'destroy'])
        ->scopeBindings()
        ->name('rooms.photos.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/folio.php';
require __DIR__.'/payments.php';
require __DIR__.'/housekeeping.php';
require __DIR__.'/reports.php';
require __DIR__.'/users.php';
require __DIR__.'/notifications.php';
require __DIR__.'/locale.php';
