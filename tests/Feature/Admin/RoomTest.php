<?php

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function roomData(RoomType $roomType, array $overrides = []): array
{
    return array_merge([
        'room_type_id' => $roomType->id,
        'number' => '101',
        'floor' => 1,
        'status' => 'clean',
    ], $overrides);
}

test('redirects guests to the login page', function () {
    Auth::forgetGuards();

    $this->get(route('admin.rooms.index'))->assertRedirect(route('login'));
});

test('creates a room and sends the user to its edit page', function () {
    $roomType = RoomType::factory()->create();

    $response = $this->post(route('admin.rooms.store'), roomData($roomType))
        ->assertSessionHasNoErrors();

    $room = Room::firstWhere('number', '101');

    $response->assertRedirect(route('admin.rooms.edit', $room));

    expect($room->roomType->is($roomType))->toBeTrue()
        ->and($room->status)->toBe(RoomStatus::Clean);
});

test('rejects invalid room data', function (array $overrides, string $field) {
    $roomType = RoomType::factory()->create();

    $this->post(route('admin.rooms.store'), roomData($roomType, $overrides))
        ->assertSessionHasErrors($field);

    expect(Room::count())->toBe(0);
})->with([
    'missing number' => [['number' => ''], 'number'],
    'missing floor' => [['floor' => ''], 'floor'],
    'unknown status' => [['status' => 'haunted'], 'status'],
    'unknown room type' => [['room_type_id' => 9999], 'room_type_id'],
]);

test('rejects a room type that has been deleted', function () {
    $roomType = RoomType::factory()->create();
    $roomType->delete();

    $this->post(route('admin.rooms.store'), roomData($roomType))
        ->assertSessionHasErrors('room_type_id');
});

test('rejects a room number already in use', function () {
    $roomType = RoomType::factory()->create();
    Room::factory()->for($roomType)->create(['number' => '101']);

    $this->post(route('admin.rooms.store'), roomData($roomType))
        ->assertSessionHasErrors('number');
});

test('allows reusing the number of a deleted room', function () {
    $roomType = RoomType::factory()->create();
    Room::factory()->for($roomType)->create(['number' => '101'])->delete();

    $this->post(route('admin.rooms.store'), roomData($roomType))
        ->assertSessionHasNoErrors();

    expect(Room::where('number', '101')->count())->toBe(1);
});

test('updates a room including its status', function () {
    $room = Room::factory()->create(['number' => '101']);

    $this->put(route('admin.rooms.update', $room), roomData($room->roomType, ['status' => 'out_of_service']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.rooms.index'));

    expect($room->refresh()->status)->toBe(RoomStatus::OutOfService);
});

test('filters the room list by status and room type', function () {
    $standard = RoomType::factory()->create();
    $suite = RoomType::factory()->create();
    Room::factory()->for($standard)->create(['number' => '101']);
    Room::factory()->for($standard)->dirty()->create(['number' => '102']);
    Room::factory()->for($suite)->dirty()->create(['number' => '201']);

    $this->get(route('admin.rooms.index', ['status' => 'dirty', 'room_type_id' => $standard->id]))
        ->assertOk()
        ->assertViewHas('rooms', fn ($rooms) => $rooms->pluck('number')->all() === ['102']);
});

test('rejects an unknown status filter', function () {
    $this->get(route('admin.rooms.index', ['status' => 'haunted']))
        ->assertSessionHasErrors('status');
});

test('soft deletes a room', function () {
    $room = Room::factory()->create();

    $this->delete(route('admin.rooms.destroy', $room))
        ->assertRedirect(route('admin.rooms.index'));

    $this->assertSoftDeleted($room);
});

test('refuses to delete an occupied room', function () {
    $room = Room::factory()->occupied()->create();

    $this->from(route('admin.rooms.index'))
        ->delete(route('admin.rooms.destroy', $room))
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($room);
});
