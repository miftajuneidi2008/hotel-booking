<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingChangeTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    private Room $roomA;

    private Room $roomB;

    private User $desk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = RoomType::factory()->create(['capacity' => 3, 'base_rate' => 1000]);
        $this->roomA = Room::factory()->for($this->type)->create();
        $this->roomB = Room::factory()->for($this->type)->create();
        $this->desk = User::factory()->receptionist()->create();
    }

    private function day(int $offset): string
    {
        return today()->addDays($offset)->toDateString();
    }

    /** A confirmed booking for nights day(5) and day(6), frozen at ETB 500 a night. */
    private function booking(?Room $room = null, array $overrides = []): Booking
    {
        return Booking::factory()->for($room ?? $this->roomA)->create($overrides + [
            'check_in' => $this->day(5),
            'check_out' => $this->day(7),
            'guests' => 2,
            'total_amount' => 1000,
            'rate_breakdown' => [$this->day(5) => 500, $this->day(6) => 500],
        ]);
    }

    private function payload(Booking $booking, array $overrides = []): array
    {
        return $overrides + [
            'checkin' => $this->day(5),
            'checkout' => $this->day(7),
            'guests' => 2,
            'room_id' => $booking->room_id,
            'guest_name' => $booking->guest_name,
            'guest_phone' => '+251911000000',
        ];
    }

    public function test_edit_page_opens_and_offers_the_current_room(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->desk)->get(route('staff.bookings.edit', $booking))
            ->assertOk()
            ->assertSee('Room '.$this->roomA->number)
            ->assertSee('Room '.$this->roomB->number);
    }

    public function test_dates_can_move_over_the_bookings_own_old_dates(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->desk)
            ->put(route('staff.bookings.update', $booking), $this->payload($booking, ['checkin' => $this->day(6), 'checkout' => $this->day(8)]))
            ->assertRedirect(route('staff.bookings.show', $booking));

        $booking->refresh();
        $this->assertSame($this->day(6), $booking->check_in->toDateString());
        $this->assertSame($this->day(8), $booking->check_out->toDateString());
        $this->assertSame($this->roomA->id, $booking->room_id);
    }

    public function test_old_nights_keep_their_price_and_new_nights_use_todays_rate(): void
    {
        $booking = $this->booking();

        // Extend by one night: 500 + 500 (frozen) + 1000 (today's rate).
        $this->actingAs($this->desk)
            ->put(route('staff.bookings.update', $booking), $this->payload($booking, ['checkout' => $this->day(8)]))
            ->assertRedirect();

        $this->assertEqualsWithDelta(2000.0, (float) $booking->fresh()->total_amount, 0.01);
    }

    public function test_room_cannot_be_changed_to_one_that_is_taken(): void
    {
        $booking = $this->booking();
        $this->booking($this->roomB, ['guest_name' => 'Someone Else']);

        $this->actingAs($this->desk)
            ->put(route('staff.bookings.update', $booking), $this->payload($booking, ['room_id' => $this->roomB->id]))
            ->assertSessionHas('error');

        $this->assertSame($this->roomA->id, $booking->fresh()->room_id);
    }

    public function test_room_can_be_changed_to_a_free_one(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->desk)
            ->put(route('staff.bookings.update', $booking), $this->payload($booking, ['room_id' => $this->roomB->id]))
            ->assertRedirect(route('staff.bookings.show', $booking));

        $this->assertSame($this->roomB->id, $booking->fresh()->room_id);
    }

    public function test_guests_cannot_exceed_the_room_capacity(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->desk)
            ->put(route('staff.bookings.update', $booking), $this->payload($booking, ['guests' => 5]))
            ->assertSessionHas('error');

        $this->assertSame(2, $booking->fresh()->guests);
    }

    public function test_stay_cannot_become_cheaper_than_the_deposit(): void
    {
        $booking = $this->booking(null, ['deposit_amount' => 900]);

        // One night only: 500, which is less than the 900 deposit.
        $this->actingAs($this->desk)
            ->put(route('staff.bookings.update', $booking), $this->payload($booking, ['checkout' => $this->day(6)]))
            ->assertSessionHas('error');

        $this->assertSame($this->day(7), $booking->fresh()->check_out->toDateString());
    }

    public function test_only_confirmed_bookings_can_be_changed(): void
    {
        $booking = $this->booking(null, ['status' => BookingStatus::CheckedIn]);

        $this->actingAs($this->desk)->get(route('staff.bookings.edit', $booking))
            ->assertRedirect(route('staff.bookings.show', $booking));

        $this->actingAs($this->desk)
            ->put(route('staff.bookings.update', $booking), $this->payload($booking, ['guest_name' => 'New Name']))
            ->assertSessionHas('error');

        $this->assertNotSame('New Name', $booking->fresh()->guest_name);
    }

    public function test_new_check_in_date_cannot_be_in_the_past(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->desk)
            ->put(route('staff.bookings.update', $booking), $this->payload($booking, ['checkin' => $this->day(-2), 'checkout' => $this->day(1)]))
            ->assertSessionHasErrors('checkin');
    }

    public function test_guests_cannot_change_bookings_from_the_desk(): void
    {
        $booking = $this->booking();
        $guest = User::factory()->create();

        $this->actingAs($guest)->get(route('staff.bookings.edit', $booking))->assertForbidden();
        $this->actingAs($guest)->put(route('staff.bookings.update', $booking), $this->payload($booking))->assertForbidden();
    }
}
