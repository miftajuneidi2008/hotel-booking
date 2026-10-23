<?php

use App\Models\Room;
use App\Models\RoomPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create());
});

test('uploads photos to a room', function () {
    $room = Room::factory()->create();

    $this->post(route('admin.rooms.photos.store', $room), [
        'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.rooms.edit', $room));

    expect($room->photos)->toHaveCount(2)
        ->and($room->photos->pluck('sort_order')->all())->toBe([1, 2]);

    $room->photos->each(fn (RoomPhoto $photo) => Storage::disk('public')->assertExists($photo->path));
});

test('rejects files that are not images', function () {
    $room = Room::factory()->create();

    $this->post(route('admin.rooms.photos.store', $room), [
        'photos' => [UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf')],
    ])->assertSessionHasErrors('photos.0');

    expect($room->photos()->count())->toBe(0);
});

test('rejects images larger than 4 MB', function () {
    $room = Room::factory()->create();

    $this->post(route('admin.rooms.photos.store', $room), [
        'photos' => [UploadedFile::fake()->image('big.jpg')->size(5000)],
    ])->assertSessionHasErrors('photos.0');
});

test('rejects an upload with no files', function () {
    $room = Room::factory()->create();

    $this->post(route('admin.rooms.photos.store', $room), [])
        ->assertSessionHasErrors('photos');
});

test('rejects uploads that would take a room over 10 photos', function () {
    $room = Room::factory()->create();
    RoomPhoto::factory()->count(9)->for($room)->create();

    $this->post(route('admin.rooms.photos.store', $room), [
        'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ])->assertSessionHasErrors('photos');

    expect($room->photos()->count())->toBe(9);
});

test('deletes a photo and its file', function () {
    $room = Room::factory()->create();
    Storage::disk('public')->put('rooms/a.jpg', 'image');
    $photo = RoomPhoto::factory()->for($room)->create(['path' => 'rooms/a.jpg']);

    $this->delete(route('admin.rooms.photos.destroy', [$room, $photo]))
        ->assertRedirect(route('admin.rooms.edit', $room));

    $this->assertModelMissing($photo);
    Storage::disk('public')->assertMissing('rooms/a.jpg');
});

test('returns 404 when deleting a photo through a different room', function () {
    $photo = RoomPhoto::factory()->create();
    $otherRoom = Room::factory()->create();

    $this->delete(route('admin.rooms.photos.destroy', [$otherRoom, $photo]))
        ->assertNotFound();

    $this->assertModelExists($photo);
});
