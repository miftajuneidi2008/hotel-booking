<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StayTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    private Room $roomA;

    private Room $roomB;

    private User $desk;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->type = RoomType::factory()->create(['capacity' => 3, 'base_rate' => 1000]);
        $this->roomA = Room::factory()->for($this->type)->create();
        $this->roomB = Room::factory()->for($this->type)->create();
        $this->desk = User::factory()->receptionist()->create();
        $this->manager = User::factory()->create(['role' => 'manager']);
    }

    private function day(int $offset): string
    {
        return today()->addDays($offset)->toDateString();
    }

    /** A confirmed booking that starts today for one night (ETB 1000). */
    private function arriving(?Room $room = null, array $overrides = []): Booking
    {
        return Booking::factory()->for($room ?? $this->roomA)->create($overrides + [
            'check_in' => $this->day(0),
            'check_out' => $this->day(1),
            'total_amount' => 1000,
            'rate_breakdown' => [$this->day(0) => 1000],
        ]);
    }

    /** A checked-in guest who arrived yesterday and leaves today (1 night, ETB 1000). */
    private function leavingToday(array $overrides = []): Booking
    {
        $this->roomA->update(['status' => RoomStatus::Occupied]);

        return Booking::factory()->for($this->roomA)->create($overrides + [
            'status' => BookingStatus::CheckedIn,
            'checked_in_at' => now()->subDay(),
            'check_in' => $this->day(-1),
            'check_out' => $this->day(0),
            'total_amount' => 1000,
            'rate_breakdown' => [$this->day(-1) => 1000],
        ]);
    }

    private function id(array $overrides = []): array
    {
        return $overrides + ['id_type' => 'national_id', 'id_number' => 'ET-123456', 'nationality' => 'Ethiopian'];
    }

    // ----- FR-11 check-in ------------------------------------------------

    public function test_check_in_saves_id_and_occupies_the_room(): void
    {
        $booking = $this->arriving();

        $this->actingAs($this->desk)->post(route('staff.bookings.check-in.store', $booking), $this->id())
            ->assertRedirect(route('staff.bookings.show', $booking));

        $booking->refresh();
        $this->assertSame(BookingStatus::CheckedIn, $booking->status);
        $this->assertSame('ET-123456', $booking->id_number);
        $this->assertSame('national_id', $booking->id_type->value);
        $this->assertSame($this->desk->id, $booking->checked_in_by);
        $this->assertNotNull($booking->checked_in_at);
        $this->assertSame(RoomStatus::Occupied, $this->roomA->fresh()->status);
    }

    public function test_check_in_needs_id_details(): void
    {
        $booking = $this->arriving();

        $this->actingAs($this->desk)->post(route('staff.bookings.check-in.store', $booking), [])
            ->assertSessionHasErrors(['id_type', 'id_number']);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_guest_cannot_be_checked_in_before_the_arrival_day(): void
    {
        $booking = $this->arriving(null, ['check_in' => $this->day(2), 'check_out' => $this->day(3)]);

        $this->actingAs($this->desk)->post(route('staff.bookings.check-in.store', $booking), $this->id())
            ->assertSessionHas('error');

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_dirty_room_blocks_check_in_until_it_is_marked_clean(): void
    {
        $this->roomA->update(['status' => RoomStatus::Dirty]);
        $booking = $this->arriving();

        $this->actingAs($this->desk)->post(route('staff.bookings.check-in.store', $booking), $this->id())
            ->assertSessionHas('error');
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);

        $this->actingAs($this->desk)->patch(route('staff.rooms.clean', $this->roomA))->assertSessionHas('status');
        $this->assertSame(RoomStatus::Clean, $this->roomA->fresh()->status);

        $this->actingAs($this->desk)->post(route('staff.bookings.check-in.store', $booking), $this->id())->assertSessionHas('status');
        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
    }

    public function test_a_cancelled_booking_cannot_be_checked_in(): void
    {
        $booking = $this->arriving(null, ['status' => BookingStatus::Cancelled]);

        $this->actingAs($this->desk)->post(route('staff.bookings.check-in.store', $booking), $this->id())
            ->assertSessionHas('error');
    }

    public function test_id_can_be_added_later_for_a_walk_in_checked_in_at_once(): void
    {
        $booking = $this->leavingToday();

        $this->actingAs($this->desk)->patch(route('staff.bookings.guest-id', $booking), $this->id(['id_type' => 'passport', 'id_number' => 'EP1234567']))
            ->assertRedirect();

        $this->assertSame('EP1234567', $booking->fresh()->id_number);
    }

    // ----- FR-13 check-out -----------------------------------------------

    public function test_check_out_with_full_payment_closes_the_bill_and_dirties_the_room(): void
    {
        $booking = $this->leavingToday(['deposit_amount' => 400]);

        $this->actingAs($this->desk)->post(route('staff.bookings.check-out.store', $booking), [
            'payment_amount' => 600, 'payment_method' => 'cash',
        ])->assertRedirect(route('staff.bookings.bill', $booking));

        $booking->refresh();
        $this->assertSame(BookingStatus::CheckedOut, $booking->status);
        $this->assertEqualsWithDelta(600.0, (float) $booking->settlement_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, $booking->balanceDue(), 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $booking->checkout_unpaid_amount, 0.01);
        $this->assertSame($this->desk->id, $booking->checked_out_by);
        $this->assertSame(RoomStatus::Dirty, $this->roomA->fresh()->status);
    }

    public function test_receptionist_cannot_check_out_with_an_unpaid_balance(): void
    {
        $booking = $this->leavingToday();

        $this->actingAs($this->desk)->post(route('staff.bookings.check-out.store', $booking), ['payment_amount' => 0, 'override_reason' => 'Promised to pay'])
            ->assertSessionHas('error');

        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
        $this->assertSame(RoomStatus::Occupied, $this->roomA->fresh()->status);
    }

    public function test_manager_can_approve_an_unpaid_check_out_but_must_give_a_reason(): void
    {
        $booking = $this->leavingToday();

        $this->actingAs($this->manager)->post(route('staff.bookings.check-out.store', $booking), ['payment_amount' => 300, 'payment_method' => 'cash'])
            ->assertSessionHas('error');
        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);

        $this->actingAs($this->manager)->post(route('staff.bookings.check-out.store', $booking), [
            'payment_amount' => 300, 'payment_method' => 'cash', 'override_reason' => 'Company will pay by transfer on Monday',
        ])->assertRedirect();

        $booking->refresh();
        $this->assertSame(BookingStatus::CheckedOut, $booking->status);
        $this->assertEqualsWithDelta(700.0, (float) $booking->checkout_unpaid_amount, 0.01);
        $this->assertSame($this->manager->id, $booking->checkout_approved_by);
        $this->assertSame('Company will pay by transfer on Monday', $booking->checkout_override_reason);
    }

    public function test_payment_cannot_be_more_than_the_balance(): void
    {
        $booking = $this->leavingToday();

        $this->actingAs($this->desk)->post(route('staff.bookings.check-out.store', $booking), ['payment_amount' => 5000, 'payment_method' => 'cash'])
            ->assertSessionHas('error');

        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
    }

    public function test_payment_needs_a_method(): void
    {
        $booking = $this->leavingToday();

        $this->actingAs($this->desk)->post(route('staff.bookings.check-out.store', $booking), ['payment_amount' => 1000])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_check_out_waits_until_the_dates_match_the_real_stay(): void
    {
        $early = $this->leavingToday(['check_out' => $this->day(2), 'rate_breakdown' => [$this->day(-1) => 1000, $this->day(0) => 1000, $this->day(1) => 1000], 'total_amount' => 3000, 'deposit_amount' => 3000]);

        $this->actingAs($this->desk)->post(route('staff.bookings.check-out.store', $early), [])->assertSessionHas('error');
        $this->assertSame(BookingStatus::CheckedIn, $early->fresh()->status);

        $early->update(['check_out' => $this->day(-0)]); // pretend it was shortened
        $this->actingAs($this->desk)->post(route('staff.bookings.check-out.store', $early), [])->assertSessionHas('status');
    }

    public function test_an_overstay_must_be_extended_before_check_out(): void
    {
        $booking = $this->leavingToday(['check_in' => $this->day(-3), 'check_out' => $this->day(-1), 'rate_breakdown' => [$this->day(-3) => 500, $this->day(-2) => 500], 'deposit_amount' => 1000]);

        $this->actingAs($this->desk)->post(route('staff.bookings.check-out.store', $booking), [])->assertSessionHas('error');
        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
    }

    public function test_a_guest_who_is_not_checked_in_cannot_be_checked_out(): void
    {
        $booking = $this->arriving();

        $this->actingAs($this->desk)->post(route('staff.bookings.check-out.store', $booking), [])->assertSessionHas('error');
    }

    public function test_statement_page_shows_the_bill(): void
    {
        $booking = $this->leavingToday(['deposit_amount' => 1000]);
        $this->actingAs($this->desk)->post(route('staff.bookings.check-out.store', $booking), []);

        $this->actingAs($this->desk)->get(route('staff.bookings.bill', $booking))
            ->assertOk()
            ->assertSee($booking->reference)
            ->assertSee('not a tax invoice');
    }

    // ----- FR-12 change room ---------------------------------------------

    public function test_moving_to_a_room_of_another_type_reprices_only_the_remaining_nights(): void
    {
        $suite = RoomType::factory()->create(['capacity' => 3, 'base_rate' => 2000]);
        $suiteRoom = Room::factory()->for($suite)->create();

        $this->roomA->update(['status' => RoomStatus::Occupied]);
        $booking = Booking::factory()->for($this->roomA)->create([
            'status' => BookingStatus::CheckedIn,
            'check_in' => $this->day(-1),
            'check_out' => $this->day(2),
            'total_amount' => 1500,
            'rate_breakdown' => [$this->day(-1) => 500, $this->day(0) => 500, $this->day(1) => 500],
        ]);

        $this->actingAs($this->desk)->post(route('staff.bookings.move-room.store', $booking), ['room_id' => $suiteRoom->id, 'reason' => 'Air conditioner broken'])
            ->assertRedirect(route('staff.bookings.show', $booking));

        $booking->refresh();
        $this->assertSame($suiteRoom->id, $booking->room_id);
        // 1 night already stayed at 500 + 2 remaining nights at 2000.
        $this->assertEqualsWithDelta(4500.0, (float) $booking->total_amount, 0.01);
        $this->assertSame(RoomStatus::Dirty, $this->roomA->fresh()->status);
        $this->assertSame(RoomStatus::Occupied, $suiteRoom->fresh()->status);

        $move = $booking->roomMoves()->firstOrFail();
        $this->assertSame($this->roomA->id, $move->from_room_id);
        $this->assertSame($suiteRoom->id, $move->to_room_id);
        $this->assertSame('Air conditioner broken', $move->reason);
    }

    public function test_moving_within_the_same_room_type_keeps_the_price(): void
    {
        $booking = $this->leavingToday(['check_out' => $this->day(2), 'rate_breakdown' => [$this->day(-1) => 500, $this->day(0) => 500, $this->day(1) => 500], 'total_amount' => 1500]);

        $this->actingAs($this->desk)->post(route('staff.bookings.move-room.store', $booking), ['room_id' => $this->roomB->id, 'reason' => 'Noise'])->assertRedirect();

        $this->assertEqualsWithDelta(1500.0, (float) $booking->fresh()->total_amount, 0.01);
        $this->assertSame($this->roomB->id, $booking->fresh()->room_id);
    }

    public function test_a_move_needs_a_reason_a_clean_room_and_a_free_room(): void
    {
        $booking = $this->leavingToday(['check_out' => $this->day(2), 'rate_breakdown' => [$this->day(-1) => 500, $this->day(0) => 500, $this->day(1) => 500], 'total_amount' => 1500]);

        $this->actingAs($this->desk)->post(route('staff.bookings.move-room.store', $booking), ['room_id' => $this->roomB->id, 'reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->roomB->update(['status' => RoomStatus::Dirty]);
        $this->actingAs($this->desk)->post(route('staff.bookings.move-room.store', $booking), ['room_id' => $this->roomB->id, 'reason' => 'Noise'])
            ->assertSessionHas('error');

        $this->roomB->update(['status' => RoomStatus::Clean]);
        Booking::factory()->for($this->roomB)->create(['check_in' => $this->day(1), 'check_out' => $this->day(3)]);
        $this->actingAs($this->desk)->post(route('staff.bookings.move-room.store', $booking), ['room_id' => $this->roomB->id, 'reason' => 'Noise'])
            ->assertSessionHas('error');

        $this->assertSame($this->roomA->id, $booking->fresh()->room_id);
        $this->assertSame(0, $booking->roomMoves()->count());
    }

    // ----- FR-14 extend or shorten ---------------------------------------

    public function test_extending_adds_nights_at_todays_rate(): void
    {
        $booking = $this->leavingToday(['check_out' => $this->day(1), 'rate_breakdown' => [$this->day(-1) => 500, $this->day(0) => 500], 'total_amount' => 1000]);

        $this->actingAs($this->desk)->put(route('staff.bookings.stay.update', $booking), ['checkout' => $this->day(3)])->assertRedirect();

        $booking->refresh();
        $this->assertSame($this->day(3), $booking->check_out->toDateString());
        // 500 + 500 stay as booked; 2 new nights at 1000.
        $this->assertEqualsWithDelta(3000.0, (float) $booking->total_amount, 0.01);
        $this->assertCount(4, $booking->rate_breakdown);
    }

    public function test_extending_is_refused_when_the_room_is_booked_next(): void
    {
        $booking = $this->leavingToday();
        Booking::factory()->for($this->roomA)->create(['check_in' => $this->day(1), 'check_out' => $this->day(3)]);

        $this->actingAs($this->desk)->put(route('staff.bookings.stay.update', $booking), ['checkout' => $this->day(2)])->assertSessionHas('error');

        $this->assertSame($this->day(0), $booking->fresh()->check_out->toDateString());
    }

    public function test_shortening_removes_the_unused_nights_from_the_bill(): void
    {
        $booking = $this->leavingToday([
            'check_out' => $this->day(3),
            'rate_breakdown' => [$this->day(-1) => 500, $this->day(0) => 500, $this->day(1) => 500, $this->day(2) => 500],
            'total_amount' => 2000,
        ]);

        $this->actingAs($this->desk)->put(route('staff.bookings.stay.update', $booking), ['checkout' => $this->day(1)])->assertRedirect();

        $booking->refresh();
        $this->assertSame($this->day(1), $booking->check_out->toDateString());
        $this->assertEqualsWithDelta(1000.0, (float) $booking->total_amount, 0.01);
        $this->assertCount(2, $booking->rate_breakdown);
    }

    public function test_a_stay_cannot_end_before_today_or_below_what_was_already_paid(): void
    {
        $booking = $this->leavingToday(['check_out' => $this->day(3), 'rate_breakdown' => [$this->day(-1) => 500, $this->day(0) => 500, $this->day(1) => 500, $this->day(2) => 500], 'total_amount' => 2000, 'deposit_amount' => 1800]);

        $this->actingAs($this->desk)->put(route('staff.bookings.stay.update', $booking), ['checkout' => $this->day(-1)])
            ->assertSessionHasErrors('checkout');

        // Shortening to tomorrow would make the total 1000, less than the 1800 already paid.
        $this->actingAs($this->desk)->put(route('staff.bookings.stay.update', $booking), ['checkout' => $this->day(1)])->assertSessionHas('error');

        $this->assertSame($this->day(3), $booking->fresh()->check_out->toDateString());
    }

    // ----- pages and access ----------------------------------------------

    public function test_front_desk_lists_arrivals_in_house_and_departures(): void
    {
        $arriving = $this->arriving($this->roomB, ['guest_name' => 'Arriving Guest']);
        $leaving = $this->leavingToday(['guest_name' => 'Leaving Guest']);

        $this->actingAs($this->desk)->get(route('staff.front-desk'))
            ->assertOk()
            ->assertSee('Arriving Guest')
            ->assertSee('Leaving Guest');

        $this->actingAs($this->desk)->get(route('staff.bookings.check-in', $arriving))->assertOk();
        $this->actingAs($this->desk)->get(route('staff.bookings.check-out', $leaving))->assertOk();
        $this->actingAs($this->desk)->get(route('staff.bookings.move-room', $leaving))->assertOk();
        $this->actingAs($this->desk)->get(route('staff.bookings.stay', $leaving))->assertOk();
        $this->actingAs($this->desk)->get(route('staff.bookings.show', $leaving))->assertOk()->assertSee('Check out');
    }

    public function test_guests_cannot_use_the_front_desk_actions(): void
    {
        $booking = $this->arriving();
        $guest = User::factory()->create();

        $this->actingAs($guest)->get(route('staff.front-desk'))->assertForbidden();
        $this->actingAs($guest)->post(route('staff.bookings.check-in.store', $booking), $this->id())->assertForbidden();
        $this->actingAs($guest)->post(route('staff.bookings.check-out.store', $booking), [])->assertForbidden();
        $this->actingAs($guest)->get(route('staff.bookings.bill', $booking))->assertForbidden();
    }
}
