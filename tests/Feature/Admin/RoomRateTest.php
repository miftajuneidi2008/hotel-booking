<?php

use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function roomRateData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Weekend rate',
        'amount' => '2200',
        'applies_on' => 'weekends',
        'starts_on' => '',
        'ends_on' => '',
    ], $overrides);
}

test('lists the rates of a room type', function () {
    $roomType = RoomType::factory()->create();
    RoomRate::factory()->weekend()->for($roomType)->create(['name' => 'Weekend special']);

    $this->get(route('admin.room-types.rates.index', $roomType))
        ->assertOk()
        ->assertSee('Weekend special');
});

test('adds a weekend rate without dates', function () {
    $roomType = RoomType::factory()->create();

    $this->post(route('admin.room-types.rates.store', $roomType), roomRateData())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.room-types.rates.index', $roomType));

    expect($roomType->rates)->toHaveCount(1)
        ->and($roomType->rates->first()->amount)->toBe('2200.00');
});

test('adds a seasonal rate with a date range', function () {
    $roomType = RoomType::factory()->create();

    $this->post(route('admin.room-types.rates.store', $roomType), roomRateData([
        'name' => 'Timkat',
        'applies_on' => 'all_days',
        'starts_on' => '2027-01-15',
        'ends_on' => '2027-01-21',
    ]))->assertSessionHasNoErrors();

    expect($roomType->rates->first()->isSeasonal())->toBeTrue();
});

test('rejects invalid rate data', function (array $overrides, string $field) {
    $roomType = RoomType::factory()->create();

    $this->post(route('admin.room-types.rates.store', $roomType), roomRateData($overrides))
        ->assertSessionHasErrors($field);

    expect($roomType->rates()->count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'negative amount' => [['amount' => -5], 'amount'],
    'unknown applies_on' => [['applies_on' => 'holidays'], 'applies_on'],
    'only a start date' => [['starts_on' => '2027-01-15'], 'ends_on'],
    'only an end date' => [['ends_on' => '2027-01-21'], 'starts_on'],
    'end before start' => [['starts_on' => '2027-01-21', 'ends_on' => '2027-01-15'], 'ends_on'],
    'every-night rate without dates' => [['applies_on' => 'all_days'], 'applies_on'],
]);

test('deletes a rate', function () {
    $roomType = RoomType::factory()->create();
    $rate = RoomRate::factory()->for($roomType)->create();

    $this->delete(route('admin.room-types.rates.destroy', [$roomType, $rate]))
        ->assertRedirect(route('admin.room-types.rates.index', $roomType));

    $this->assertModelMissing($rate);
});

test('returns 404 when deleting a rate through a different room type', function () {
    $rate = RoomRate::factory()->create();
    $otherType = RoomType::factory()->create();

    $this->delete(route('admin.room-types.rates.destroy', [$otherType, $rate]))
        ->assertNotFound();
});

describe('rate for a date', function () {
    // 2026-10-09 is a Friday, 2026-10-11 a Sunday.
    test('falls back to the base rate when no rate matches', function () {
        $roomType = RoomType::factory()->create(['base_rate' => 1800]);

        expect($roomType->rateForDate(Carbon::parse('2026-10-09')))->toBe('1800.00');
    });

    test('uses the weekend rate on Friday and Saturday nights only', function () {
        $roomType = RoomType::factory()->create(['base_rate' => 1800]);
        RoomRate::factory()->weekend()->for($roomType)->create(['amount' => 2200]);

        expect($roomType->rateForDate(Carbon::parse('2026-10-09')))->toBe('2200.00')
            ->and($roomType->rateForDate(Carbon::parse('2026-10-10')))->toBe('2200.00')
            ->and($roomType->rateForDate(Carbon::parse('2026-10-11')))->toBe('1800.00');
    });

    test('uses a seasonal rate only inside its date range, inclusive', function () {
        $roomType = RoomType::factory()->create(['base_rate' => 1800]);
        RoomRate::factory()->seasonal('2026-10-10', '2026-10-12')->for($roomType)->create(['amount' => 3000]);

        expect($roomType->rateForDate(Carbon::parse('2026-10-09')))->toBe('1800.00')
            ->and($roomType->rateForDate(Carbon::parse('2026-10-10')))->toBe('3000.00')
            ->and($roomType->rateForDate(Carbon::parse('2026-10-12')))->toBe('3000.00')
            ->and($roomType->rateForDate(Carbon::parse('2026-10-13')))->toBe('1800.00');
    });

    test('prefers a seasonal rate over a weekend rate on the same night', function () {
        $roomType = RoomType::factory()->create(['base_rate' => 1800]);
        RoomRate::factory()->weekend()->for($roomType)->create(['amount' => 2200]);
        RoomRate::factory()->seasonal('2026-10-01', '2026-10-31')->for($roomType)->create(['amount' => 3000]);

        expect($roomType->rateForDate(Carbon::parse('2026-10-09')))->toBe('3000.00');
    });

    test('uses the newest rate when two rates are equally specific', function () {
        $roomType = RoomType::factory()->create(['base_rate' => 1800]);
        RoomRate::factory()->seasonal('2026-10-01', '2026-10-31')->for($roomType)->create(['amount' => 2500]);
        RoomRate::factory()->seasonal('2026-10-01', '2026-10-31')->for($roomType)->create(['amount' => 2700]);

        expect($roomType->rateForDate(Carbon::parse('2026-10-09')))->toBe('2700.00');
    });
});
