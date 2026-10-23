<?php

use App\Models\Room;
use App\Models\RoomPhoto;
use App\Models\RoomRate;
use App\Models\RoomType;

test('shows rooms to guests without logging in', function () {
    $roomType = RoomType::factory()->create(['name' => 'Deluxe Room']);
    Room::factory()->for($roomType)->create(['number' => '201']);

    $this->get(route('rooms'))
        ->assertOk()
        ->assertSee('Deluxe Room')
        ->assertSee('Room 201');
});

test('hides out of service and deleted rooms', function () {
    Room::factory()->create(['number' => '301']);
    Room::factory()->outOfService()->create(['number' => '302']);
    Room::factory()->create(['number' => '303'])->delete();

    $this->get(route('rooms'))
        ->assertSee('Room 301')
        ->assertDontSee('Room 302')
        ->assertDontSee('Room 303');
});

test('prices each room with tonight\'s rate', function () {
    $this->travelTo('2026-10-09'); // a Friday

    $roomType = RoomType::factory()->create(['base_rate' => 1800]);
    RoomRate::factory()->weekend()->for($roomType)->create(['amount' => 2200]);
    Room::factory()->for($roomType)->create();

    $this->get(route('rooms'))->assertSee('ETB 2,200');
});

test('shows a message when there are no rooms', function () {
    $this->get(route('rooms'))->assertOk()->assertSee('No rooms are available');
});

test('links the Rooms item in the navbar to the rooms page', function () {
    $this->get('/')->assertSee(route('rooms'), false);
});

test('links each room type on the home page to its filtered room list', function () {
    $this->get('/')
        ->assertSee(route('rooms', ['type' => 'standard-room']), false)
        ->assertSee(route('rooms', ['type' => 'family-suite']), false);
});

describe('filtering by room type', function () {
    beforeEach(function () {
        $this->standard = RoomType::factory()->create(['name' => 'Standard Room']);
        $this->deluxe = RoomType::factory()->create(['name' => 'Deluxe Room']);
        Room::factory()->for($this->standard)->create(['number' => '101']);
        Room::factory()->for($this->deluxe)->create(['number' => '201']);
        Room::factory()->for($this->deluxe)->create(['number' => '202']);
    });

    test('lists only the rooms of the selected type', function () {
        $this->get(route('rooms', ['type' => 'deluxe-room']))
            ->assertOk()
            ->assertSee('Room 201')
            ->assertSee('Room 202')
            ->assertDontSee('Room 101');
    });

    test('marks the selected type as current in the navigation', function () {
        $this->get(route('rooms', ['type' => 'deluxe-room']))
            ->assertSee('aria-current="page"', false)
            ->assertSee('Filter rooms by type')
            ->assertSee(route('rooms', ['type' => 'standard-room']), false);
    });

    test('shows every room when no type is selected', function () {
        $this->get(route('rooms'))
            ->assertSee('Room 101')
            ->assertSee('Room 201');
    });

    test('says so when the room type does not exist', function () {
        $this->get(route('rooms', ['type' => 'castle']))
            ->assertOk()
            ->assertSee('We could not find that room type')
            ->assertDontSee('Room 101');
    });

    test('says so when the room type has no available rooms', function () {
        RoomType::factory()->create(['name' => 'Penthouse']);

        $this->get(route('rooms', ['type' => 'penthouse']))
            ->assertOk()
            ->assertSee('No rooms of this type are available');
    });
});

describe('room detail page', function () {
    test('shows the room, its type details and tonight\'s price', function () {
        $this->travelTo('2026-10-07'); // a Wednesday

        $roomType = RoomType::factory()->create([
            'name' => 'Deluxe Room',
            'description' => 'More space and a quiet view.',
            'capacity' => 3,
            'base_rate' => 2600,
        ]);
        RoomRate::factory()->weekend()->for($roomType)->create(['name' => 'Weekend special', 'amount' => 3100]);
        $room = Room::factory()->for($roomType)->create(['number' => '201', 'floor' => 2]);

        $this->get(route('rooms.show', $room))
            ->assertOk()
            ->assertSee('Deluxe Room')
            ->assertSee('Room 201')
            ->assertSee('More space and a quiet view.')
            ->assertSee('Up to 3')
            ->assertSee('ETB 2,600')
            ->assertSee('Weekend special')
            ->assertSee('ETB 3,100');
    });

    test('hides seasonal rates that have already ended', function () {
        $this->travelTo('2026-10-07');

        $roomType = RoomType::factory()->create();
        RoomRate::factory()->seasonal('2026-01-01', '2026-01-10')->for($roomType)->create(['name' => 'Old season']);
        RoomRate::factory()->seasonal('2026-12-20', '2027-01-05')->for($roomType)->create(['name' => 'Holiday season']);
        $room = Room::factory()->for($roomType)->create();

        $this->get(route('rooms.show', $room))
            ->assertSee('Holiday season')
            ->assertDontSee('Old season');
    });

    test('shows the uploaded photos of the room', function () {
        $room = Room::factory()->create();
        RoomPhoto::factory()->for($room)->create(['path' => 'rooms/1/lobby-view.jpg']);

        $this->get(route('rooms.show', $room))->assertSee('storage/rooms/1/lobby-view.jpg', false);
    });

    test('links back to the filtered list of its room type', function () {
        $room = Room::factory()->for(RoomType::factory()->create(['name' => 'Deluxe Room']))->create();

        $this->get(route('rooms.show', $room))->assertSee(route('rooms', ['type' => 'deluxe-room']), false);
    });

    test('returns 404 for an out of service room', function () {
        $this->get(route('rooms.show', Room::factory()->outOfService()->create()))->assertNotFound();
    });

    test('returns 404 for a deleted room', function () {
        $room = Room::factory()->create();
        $room->delete();

        $this->get(route('rooms.show', $room))->assertNotFound();
    });
});
