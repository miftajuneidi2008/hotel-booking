<?php

use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function roomTypeData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Suite',
        'description' => 'Two rooms for families.',
        'capacity' => 4,
        'base_rate' => '4200.50',
    ], $overrides);
}

test('redirects guests to the login page', function () {
    Auth::forgetGuards();

    $this->get(route('admin.room-types.index'))->assertRedirect(route('login'));
});

test('lists room types with their room counts', function () {
    $roomType = RoomType::factory()->create(['name' => 'Deluxe Room']);
    Room::factory()->count(2)->for($roomType)->create();

    $this->get(route('admin.room-types.index'))
        ->assertOk()
        ->assertSee('Deluxe Room')
        ->assertViewHas('roomTypes', fn ($roomTypes) => (int) $roomTypes->first()->rooms_count === 2);
});

test('creates a room type', function () {
    $this->post(route('admin.room-types.store'), roomTypeData())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.room-types.index'));

    $roomType = RoomType::firstWhere('name', 'Suite');

    expect($roomType->capacity)->toBe(4)
        ->and($roomType->base_rate)->toBe('4200.50');
});

test('rejects invalid room type data', function (array $overrides, string $field) {
    $this->post(route('admin.room-types.store'), roomTypeData($overrides))
        ->assertSessionHasErrors($field);

    expect(RoomType::count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'zero capacity' => [['capacity' => 0], 'capacity'],
    'negative base rate' => [['base_rate' => -1], 'base_rate'],
]);

test('rejects a name already used by another room type', function () {
    RoomType::factory()->create(['name' => 'Suite']);

    $this->post(route('admin.room-types.store'), roomTypeData())
        ->assertSessionHasErrors('name');
});

test('allows reusing the name of a deleted room type', function () {
    RoomType::factory()->create(['name' => 'Suite'])->delete();

    $this->post(route('admin.room-types.store'), roomTypeData())
        ->assertSessionHasNoErrors();

    expect(RoomType::where('name', 'Suite')->count())->toBe(1);
});

test('updates a room type and keeps its own name valid', function () {
    $roomType = RoomType::factory()->create(['name' => 'Suite']);

    $this->put(route('admin.room-types.update', $roomType), roomTypeData(['capacity' => 6]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.room-types.index'));

    expect($roomType->refresh()->capacity)->toBe(6);
});

test('soft deletes a room type without rooms', function () {
    $roomType = RoomType::factory()->create();

    $this->delete(route('admin.room-types.destroy', $roomType))
        ->assertRedirect(route('admin.room-types.index'));

    $this->assertSoftDeleted($roomType);
});

test('refuses to delete a room type that still has rooms', function () {
    $roomType = RoomType::factory()->create();
    Room::factory()->for($roomType)->create();

    $this->from(route('admin.room-types.index'))
        ->delete(route('admin.room-types.destroy', $roomType))
        ->assertRedirect(route('admin.room-types.index'))
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($roomType);
});

test('gives every new room type a slug made from its name', function () {
    expect(RoomType::factory()->create(['name' => 'Family Suite'])->slug)->toBe('family-suite');
});

test('adds a number to a slug that was already used, even by a deleted room type', function () {
    RoomType::factory()->create(['name' => 'Suite'])->delete();

    expect(RoomType::factory()->create(['name' => 'Suite'])->slug)->toBe('suite-2');
});

test('keeps the slug when a room type is renamed', function () {
    $roomType = RoomType::factory()->create(['name' => 'Suite']);

    $this->put(route('admin.room-types.update', $roomType), roomTypeData(['name' => 'Royal Suite']))
        ->assertSessionHasNoErrors();

    expect($roomType->refresh()->slug)->toBe('suite');
});
