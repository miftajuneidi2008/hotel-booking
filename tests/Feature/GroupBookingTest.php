<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupBookingTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    /** @var list<Room> */
    private array $rooms;

    private User $desk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = RoomType::factory()->create(['capacity' => 2, 'base_rate' => 1000]);
        $this->rooms = [
            Room::factory()->for($this->type)->create(),
            Room::factory()->for($this->type)->create(),
            Room::factory()->for($this->type)->create(),
        ];
        $this->desk = User::factory()->receptionist()->create();
    }

    private function payload(array $roomIds, array $overrides = []): array
    {
        return $overrides + [
            'checkin' => today()->addDays(10)->toDateString(),
            'checkout' => today()->addDays(12)->toDateString(),
            'room_ids' => $roomIds,
            'guests' => array_fill_keys($roomIds, 2),
            'source' => 'phone',
            'name' => 'Abay Trading PLC',
            'contact_name' => 'Hana',
            'contact_phone' => '+251 911 000 000',
        ];
    }

    public function test_several_rooms_are_booked_together_for_one_group(): void
    {
        $ids = [$this->rooms[0]->id, $this->rooms[1]->id];

        $this->actingAs($this->desk)->post(route('staff.groups.store'), $this->payload($ids))->assertRedirect();

        $group = BookingGroup::query()->firstOrFail();
        $this->assertStringStartsWith('GR-', $group->reference);
        $this->assertSame(2, $group->bookings()->count());
        $this->assertEqualsCanonicalizing($ids, $group->bookings()->pluck('room_id')->all());

        $booking = $group->bookings()->first();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame('Abay Trading PLC', $booking->guest_name);
        $this->assertSame($this->desk->id, $booking->created_by);
        $this->assertEqualsWithDelta(2000.0, (float) $booking->total_amount, 0.01);
        $this->assertEqualsWithDelta(4000.0, $group->load('bookings')->activeTotal(), 0.01);
    }

    public function test_it_is_all_or_nothing_when_one_room_is_taken(): void
    {
        $taken = $this->rooms[1];
        Booking::factory()->for($taken)->create([
            'check_in' => today()->addDays(11)->toDateString(),
            'check_out' => today()->addDays(13)->toDateString(),
        ]);

        $this->actingAs($this->desk)
            ->post(route('staff.groups.store'), $this->payload([$this->rooms[0]->id, $taken->id]))
            ->assertSessionHas('error');

        $this->assertSame(0, BookingGroup::query()->count());
        $this->assertSame(1, Booking::query()->count()); // only the one that was already there
    }

    public function test_group_rooms_block_single_bookings(): void
    {
        $this->actingAs($this->desk)->post(route('staff.groups.store'), $this->payload([$this->rooms[0]->id]));

        $free = app(BookingService::class)->availableRooms(
            today()->addDays(10)->toDateString(),
            today()->addDays(12)->toDateString(),
            1,
        );

        $this->assertFalse($free->contains('id', $this->rooms[0]->id));
        $this->assertCount(2, $free);
    }

    public function test_guests_per_room_cannot_exceed_room_capacity(): void
    {
        $id = $this->rooms[0]->id;

        $this->actingAs($this->desk)
            ->post(route('staff.groups.store'), $this->payload([$id], ['guests' => [$id => 4]]))
            ->assertSessionHas('error');

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_at_least_one_room_is_needed(): void
    {
        $this->actingAs($this->desk)
            ->post(route('staff.groups.store'), $this->payload([], ['room_ids' => []]))
            ->assertSessionHasErrors('room_ids');
    }

    public function test_cancelling_a_group_needs_a_reason_and_cancels_every_confirmed_room(): void
    {
        $this->actingAs($this->desk)->post(route('staff.groups.store'), $this->payload([$this->rooms[0]->id, $this->rooms[1]->id]));
        $group = BookingGroup::query()->firstOrFail();

        $this->actingAs($this->desk)->patch(route('staff.groups.cancel', $group), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->desk)->patch(route('staff.groups.cancel', $group), ['reason' => 'Event called off'])
            ->assertRedirect(route('staff.groups.show', $group));

        $this->assertSame(0, $group->bookings()->where('status', BookingStatus::Confirmed->value)->count());
        $this->assertSame(2, $group->bookings()->where('status', BookingStatus::Cancelled->value)->count());
        $this->assertSame('Event called off', $group->bookings()->first()->cancellation_reason);
    }

    public function test_pages_render_for_staff(): void
    {
        $this->actingAs($this->desk)->post(route('staff.groups.store'), $this->payload([$this->rooms[0]->id]));
        $group = BookingGroup::query()->firstOrFail();

        $this->actingAs($this->desk)->get(route('staff.groups.index'))->assertOk()->assertSee('Abay Trading PLC');
        $this->actingAs($this->desk)->get(route('staff.groups.show', $group))->assertOk()->assertSee($group->reference);
        $this->actingAs($this->desk)
            ->get(route('staff.groups.create', ['checkin' => today()->addDays(10)->toDateString(), 'checkout' => today()->addDays(12)->toDateString()]))
            ->assertOk();
        $this->actingAs($this->desk)->get(route('staff.bookings.show', $group->bookings()->first()))->assertOk()->assertSee($group->reference);
    }

    public function test_guests_cannot_use_group_booking(): void
    {
        $guest = User::factory()->create();

        $this->actingAs($guest)->get(route('staff.groups.index'))->assertForbidden();
        $this->actingAs($guest)->post(route('staff.groups.store'), $this->payload([$this->rooms[0]->id]))->assertForbidden();
    }
}
