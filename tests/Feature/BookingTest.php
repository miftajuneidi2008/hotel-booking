<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingService;
use App\Exceptions\BookingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function dates(int $in = 5, int $nights = 2): array
    {
        return [
            'checkin' => today()->addDays($in)->toDateString(),
            'checkout' => today()->addDays($in + $nights)->toDateString(),
            'guests' => 2,
        ];
    }

    private function guestPayload(RoomType $type, array $dates): array
    {
        return $dates + [
            'type' => $type->slug,
            'guest_name' => 'Abebe Kebede',
            'guest_phone' => '+251 911 000 000',
        ];
    }

    public function test_search_lists_only_room_types_with_a_free_room(): void
    {
        $free = RoomType::factory()->create(['capacity' => 2, 'base_rate' => 1000]);
        Room::factory()->for($free)->create();

        $full = RoomType::factory()->create(['capacity' => 2]);
        $room = Room::factory()->for($full)->create();
        $dates = $this->dates();
        Booking::factory()->for($room)->create(['check_in' => $dates['checkin'], 'check_out' => $dates['checkout']]);

        $this->get(route('booking', $dates))
            ->assertOk()
            ->assertSee($free->name)
            ->assertDontSee($full->name);
    }

    public function test_guest_must_log_in_to_book_but_is_sent_back_afterwards(): void
    {
        $type = RoomType::factory()->create(['capacity' => 2]);
        Room::factory()->for($type)->create();

        $this->get(route('booking.review', $this->guestPayload($type, $this->dates())))
            ->assertRedirect(route('login'));
    }

    public function test_guest_can_book_and_a_free_room_is_assigned(): void
    {
        $type = RoomType::factory()->create(['capacity' => 2, 'base_rate' => 1000]);
        $room = Room::factory()->for($type)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('booking.store'), $this->guestPayload($type, $this->dates()));

        $booking = Booking::query()->firstOrFail();
        $response->assertRedirect(route('my-bookings.show', $booking));

        $this->assertSame($room->id, $booking->room_id);
        $this->assertSame($user->id, $booking->user_id);
        $this->assertSame('online', $booking->source->value);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertEqualsWithDelta(2000.0, (float) $booking->total_amount, 0.01);
    }

    public function test_two_bookings_cannot_overlap_on_the_same_room(): void
    {
        $type = RoomType::factory()->create(['capacity' => 2]);
        Room::factory()->for($type)->create(); // only one room
        $first = User::factory()->create();
        $second = User::factory()->create();
        $payload = $this->guestPayload($type, $this->dates());

        $this->actingAs($first)->post(route('booking.store'), $payload);
        $this->actingAs($second)->post(route('booking.store'), $payload)->assertSessionHas('error');

        $this->assertSame(1, Booking::query()->count());
    }

    public function test_a_guest_can_arrive_on_the_day_another_guest_leaves(): void
    {
        $type = RoomType::factory()->create(['capacity' => 2]);
        $room = Room::factory()->for($type)->create();
        $service = app(BookingService::class);

        $first = $this->dates(5, 2);
        $second = $this->dates(7, 2); // arrives the day the first guest leaves

        $user = User::factory()->create();
        $data = fn (array $d) => ['check_in' => $d['checkin'], 'check_out' => $d['checkout'], 'guests' => 2, 'guest_name' => 'A', 'guest_phone' => '+251911000000'];

        $service->bookRoomType($type, $data($first), $user);
        $service->bookRoomType($type, $data($second), $user);

        $this->assertSame(2, $room->bookings()->count());

        $this->expectException(BookingException::class);
        $service->bookRoomType($type, $data($this->dates(6, 2)), $user); // overlaps both
    }

    public function test_cancelled_booking_frees_the_room(): void
    {
        $type = RoomType::factory()->create(['capacity' => 2]);
        $room = Room::factory()->for($type)->create();
        $dates = $this->dates();
        Booking::factory()->for($room)->cancelled()->create(['check_in' => $dates['checkin'], 'check_out' => $dates['checkout']]);

        $this->assertCount(1, app(BookingService::class)->availableRooms($dates['checkin'], $dates['checkout'], 2));
    }

    public function test_out_of_service_rooms_are_never_offered(): void
    {
        $type = RoomType::factory()->create(['capacity' => 2]);
        Room::factory()->for($type)->outOfService()->create();
        $dates = $this->dates();

        $this->assertCount(0, app(BookingService::class)->availableRooms($dates['checkin'], $dates['checkout'], 2));
    }

    public function test_guests_cannot_exceed_room_capacity(): void
    {
        $type = RoomType::factory()->create(['capacity' => 2]);
        Room::factory()->for($type)->create();
        $dates = ['guests' => 3] + $this->dates();

        $this->actingAs(User::factory()->create())
            ->post(route('booking.store'), $this->guestPayload($type, $dates))
            ->assertSessionHas('error');

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_guest_cannot_see_or_cancel_someone_elses_booking(): void
    {
        $booking = Booking::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('my-bookings.show', $booking))->assertForbidden();
        $this->actingAs($stranger)->patch(route('my-bookings.cancel', $booking), ['reason' => 'No'])->assertForbidden();
    }

    public function test_cancelling_needs_a_reason(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->patch(route('my-bookings.cancel', $booking), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($user)->patch(route('my-bookings.cancel', $booking), ['reason' => 'Plans changed'])
            ->assertRedirect();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame('Plans changed', $booking->fresh()->cancellation_reason);
    }

    public function test_guests_cannot_open_the_front_desk(): void
    {
        $this->actingAs(User::factory()->create())->get(route('staff.bookings.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.rooms.index'))->assertForbidden();
    }

    public function test_receptionist_can_book_a_walk_in_and_check_them_in(): void
    {
        $type = RoomType::factory()->create(['capacity' => 2, 'base_rate' => 1500]);
        $room = Room::factory()->for($type)->create(); // clean
        $desk = User::factory()->receptionist()->create();

        $payload = [
            'checkin' => today()->toDateString(),
            'checkout' => today()->addDay()->toDateString(),
            'guests' => 1,
            'room_id' => $room->id,
            'source' => 'walk_in',
            'guest_name' => 'Walk In',
            'guest_phone' => '0911000000',
            'deposit_amount' => 500,
            'deposit_method' => 'cash',
            'check_in_now' => '1',
        ];

        $this->actingAs($desk)->post(route('staff.bookings.store'), $payload)->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame(BookingStatus::CheckedIn, $booking->status);
        $this->assertSame($desk->id, $booking->created_by);
        $this->assertEqualsWithDelta(500.0, (float) $booking->deposit_amount, 0.01);
        $this->assertSame(RoomStatus::Occupied, $room->fresh()->status);
    }

    public function test_deposit_needs_a_payment_method_and_cannot_exceed_the_total(): void
    {
        $type = RoomType::factory()->create(['capacity' => 2, 'base_rate' => 1000]);
        $room = Room::factory()->for($type)->create();
        $desk = User::factory()->receptionist()->create();
        $base = $this->dates() + ['room_id' => $room->id, 'source' => 'phone', 'guest_name' => 'X', 'guest_phone' => '0911000000'];

        $this->actingAs($desk)->post(route('staff.bookings.store'), $base + ['deposit_amount' => 100])
            ->assertSessionHasErrors('deposit_method');

        $this->actingAs($desk)->post(route('staff.bookings.store'), $base + ['deposit_amount' => 99999, 'deposit_method' => 'cash'])
            ->assertSessionHas('error');

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_walk_in_cannot_be_checked_in_to_a_dirty_room(): void
    {
        $room = Room::factory()->dirty()->for(RoomType::factory()->create(['capacity' => 2]))->create();
        $desk = User::factory()->receptionist()->create();

        $this->actingAs($desk)->post(route('staff.bookings.store'), [
            'checkin' => today()->toDateString(),
            'checkout' => today()->addDay()->toDateString(),
            'guests' => 1,
            'room_id' => $room->id,
            'source' => 'walk_in',
            'guest_name' => 'Walk In',
            'guest_phone' => '0911000000',
            'check_in_now' => '1',
        ])->assertSessionHas('error');

        $this->assertSame(0, Booking::query()->count());
    }
}
