<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_shows_rooms_and_bookings(): void
    {
        $room = Room::factory()->for(RoomType::factory()->create())->create();
        Booking::factory()->for($room)->create([
            'guest_name' => 'Calendar Guest',
            'check_in' => today()->addDays(2)->toDateString(),
            'check_out' => today()->addDays(4)->toDateString(),
        ]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('staff.calendar'))
            ->assertOk()
            ->assertSee($room->number)
            ->assertSee('Calendar Guest');
    }

    public function test_cancelled_bookings_are_not_drawn(): void
    {
        $room = Room::factory()->for(RoomType::factory()->create())->create();
        Booking::factory()->for($room)->cancelled()->create([
            'guest_name' => 'Gone Guest',
            'check_in' => today()->addDays(2)->toDateString(),
            'check_out' => today()->addDays(4)->toDateString(),
        ]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('staff.calendar'))
            ->assertOk()
            ->assertDontSee('Gone Guest');
    }

    public function test_a_stay_that_started_before_the_window_is_still_shown(): void
    {
        $room = Room::factory()->for(RoomType::factory()->create())->create();
        Booking::factory()->for($room)->create([
            'status' => BookingStatus::CheckedIn,
            'guest_name' => 'Long Stay',
            'check_in' => today()->subDays(3)->toDateString(),
            'check_out' => today()->addDays(3)->toDateString(),
        ]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('staff.calendar'))
            ->assertOk()
            ->assertSee('Long Stay');
    }

    public function test_window_can_be_moved_and_bad_input_is_rejected(): void
    {
        $desk = User::factory()->receptionist()->create();

        $this->actingAs($desk)->get(route('staff.calendar', ['from' => today()->addDays(14)->toDateString(), 'days' => 7]))->assertOk();
        $this->actingAs($desk)->get(route('staff.calendar', ['days' => 99]))->assertSessionHasErrors('days');
    }

    public function test_guests_cannot_open_the_calendar(): void
    {
        $this->actingAs(User::factory()->create())->get(route('staff.calendar'))->assertForbidden();
    }
}
