<?php

namespace Tests\Feature\Housekeeping;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\MaintenanceIssue;
use App\Models\Room;
use App\Models\RoomStatusChange;
use App\Models\User;
use App\Services\HousekeepingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Housekeeping, BRD section 5.6.
 */
class HousekeepingTest extends TestCase
{
    use RefreshDatabase;

    private HousekeepingService $housekeeping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->housekeeping = app(HousekeepingService::class);
    }

    private function user(UserRole $role = UserRole::Housekeeping): User
    {
        return User::factory()->role($role)->create();
    }

    // ---- FR-24 room status ---------------------------------------------------

    public function test_a_dirty_room_is_marked_clean_and_the_change_is_logged(): void
    {
        $room = Room::factory()->dirty()->create();
        $user = $this->user();

        $this->housekeeping->changeStatus($room, RoomStatus::Clean, $user, 'Fresh sheets');

        $this->assertSame(RoomStatus::Clean, $room->fresh()->status);
        $this->assertDatabaseHas('room_status_changes', [
            'room_id' => $room->id,
            'from_status' => 'dirty',
            'to_status' => 'clean',
            'note' => 'Fresh sheets',
            'changed_by' => $user->id,
        ]);
    }

    public function test_a_clean_room_can_be_sent_back_to_dirty(): void
    {
        $room = Room::factory()->create();

        $this->housekeeping->changeStatus($room, RoomStatus::Dirty, $this->user());

        $this->assertSame(RoomStatus::Dirty, $room->fresh()->status);
    }

    public function test_the_same_status_is_refused(): void
    {
        $this->expectException(BookingException::class);

        $this->housekeeping->changeStatus(Room::factory()->create(), RoomStatus::Clean, $this->user());
    }

    public function test_housekeeping_cannot_set_occupied_or_out_of_service_directly(): void
    {
        $room = Room::factory()->create();

        foreach ([RoomStatus::Occupied, RoomStatus::OutOfService] as $status) {
            try {
                $this->housekeeping->changeStatus($room, $status, $this->user());
                $this->fail('Only check-in and a maintenance issue may set this status.');
            } catch (BookingException) {
                $this->assertSame(RoomStatus::Clean, $room->fresh()->status);
            }
        }
    }

    public function test_a_room_with_a_guest_in_it_cannot_be_changed(): void
    {
        $room = Room::factory()->occupied()->create();
        Booking::factory()->create(['room_id' => $room->id, 'status' => BookingStatus::CheckedIn]);

        $this->expectException(BookingException::class);

        $this->housekeeping->changeStatus($room, RoomStatus::Clean, $this->user());
    }

    // ---- FR-26 maintenance issues --------------------------------------------

    public function test_an_issue_can_be_reported_without_blocking_the_room(): void
    {
        $room = Room::factory()->create();
        $user = $this->user();

        $issue = $this->housekeeping->reportIssue($room, ['description' => 'Dripping tap', 'priority' => 'low'], false, $user);

        $this->assertFalse($issue->blocks_room);
        $this->assertSame($user->id, $issue->reported_by);
        $this->assertSame(RoomStatus::Clean, $room->fresh()->status);
        $this->assertSame(0, RoomStatusChange::query()->count());
    }

    public function test_a_blocking_issue_takes_the_room_out_of_service(): void
    {
        $room = Room::factory()->dirty()->create();

        $this->housekeeping->reportIssue($room, ['description' => 'Broken window', 'priority' => 'urgent'], true, $this->user());

        $this->assertSame(RoomStatus::OutOfService, $room->fresh()->status);
        $this->assertDatabaseHas('room_status_changes', ['room_id' => $room->id, 'from_status' => 'dirty', 'to_status' => 'out_of_service']);
        // Out of service rooms are not offered for new bookings.
        $this->assertFalse(Room::query()->availableBetween(today()->toDateString(), today()->addDay()->toDateString())->whereKey($room->id)->exists());
    }

    public function test_an_occupied_room_cannot_be_taken_out_of_service(): void
    {
        $room = Room::factory()->occupied()->create();
        Booking::factory()->create(['room_id' => $room->id, 'status' => BookingStatus::CheckedIn]);

        $this->expectException(BookingException::class);

        $this->housekeeping->reportIssue($room, ['description' => 'Leak'], true, $this->user());
    }

    public function test_a_description_is_required(): void
    {
        $this->expectException(BookingException::class);

        $this->housekeeping->reportIssue(Room::factory()->create(), ['description' => '  '], false, $this->user());
    }

    public function test_fixing_the_last_blocking_issue_puts_the_room_back_as_dirty(): void
    {
        $room = Room::factory()->create();
        $user = $this->user();

        $first = $this->housekeeping->reportIssue($room, ['description' => 'Leak'], true, $user);
        $second = $this->housekeeping->reportIssue($room, ['description' => 'No power'], true, $user);

        $this->housekeeping->resolveIssue($first, 'Pipe replaced', $user);
        $this->assertSame(RoomStatus::OutOfService, $room->fresh()->status, 'Another issue still blocks the room.');

        $this->housekeeping->resolveIssue($second, null, $user);

        $this->assertSame(RoomStatus::Dirty, $room->fresh()->status);
        $this->assertNotNull($first->fresh()->resolved_at);
        $this->assertSame('Pipe replaced', $first->fresh()->resolution_note);
    }

    public function test_an_issue_cannot_be_fixed_twice(): void
    {
        $issue = MaintenanceIssue::factory()->create();
        $user = $this->user();

        $this->housekeeping->resolveIssue($issue, null, $user);

        $this->expectException(BookingException::class);
        $this->housekeeping->resolveIssue($issue, null, $user);
    }

    public function test_a_room_with_an_open_blocking_issue_cannot_be_released_by_status(): void
    {
        $room = Room::factory()->create();
        $user = $this->user();
        $this->housekeeping->reportIssue($room, ['description' => 'Leak'], true, $user);

        $this->expectException(BookingException::class);
        $this->housekeeping->changeStatus($room, RoomStatus::Clean, $user);
    }

    public function test_upcoming_bookings_are_counted_for_the_warning(): void
    {
        $room = Room::factory()->create();
        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in' => today()->addDays(2)->toDateString(),
            'check_out' => today()->addDays(4)->toDateString(),
        ]);

        $this->assertSame(1, $this->housekeeping->upcomingBookings($room));
    }

    // ---- FR-25 phone or tablet screens and permissions -----------------------

    public function test_housekeeping_staff_see_the_board_and_mark_a_room_clean(): void
    {
        $room = Room::factory()->dirty()->create();
        $user = $this->user();

        $this->actingAs($user)->get(route('housekeeping.board'))->assertOk()->assertSee($room->number);
        $this->actingAs($user)->get(route('housekeeping.rooms.show', $room))->assertOk();

        $this->actingAs($user)->patch(route('housekeeping.rooms.status', $room), ['status' => 'clean'])
            ->assertSessionHas('status');

        $this->assertSame(RoomStatus::Clean, $room->fresh()->status);
    }

    public function test_housekeeping_can_report_and_fix_an_issue_from_the_screens(): void
    {
        $room = Room::factory()->create();
        $user = $this->user();

        $this->actingAs($user)->post(route('housekeeping.issues.store', $room), [
            'description' => 'Light does not work',
            'priority' => 'normal',
        ])->assertRedirect(route('housekeeping.rooms.show', $room));

        $issue = MaintenanceIssue::query()->firstOrFail();

        $this->actingAs($user)->get(route('housekeeping.issues.index'))->assertOk()->assertSee('Light does not work');
        $this->actingAs($user)->patch(route('housekeeping.issues.resolve', $issue), ['note' => 'New bulb'])->assertSessionHas('status');

        $this->assertNotNull($issue->fresh()->resolved_at);
    }

    public function test_the_status_screen_only_accepts_clean_or_dirty(): void
    {
        $room = Room::factory()->create();

        $this->actingAs($this->user())->patch(route('housekeeping.rooms.status', $room), ['status' => 'out_of_service'])
            ->assertSessionHasErrors('status');
    }

    public function test_the_front_desk_can_use_the_board_too(): void
    {
        $this->actingAs($this->user(UserRole::Receptionist))->get(route('housekeeping.board'))->assertOk();
    }

    public function test_cashiers_and_guest_accounts_cannot_open_housekeeping(): void
    {
        $room = Room::factory()->dirty()->create();

        foreach ([UserRole::Cashier, UserRole::Guest] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('housekeeping.board'))->assertForbidden();
            $this->actingAs($user)->patch(route('housekeeping.rooms.status', $room), ['status' => 'clean'])->assertForbidden();
        }

        $this->assertSame(RoomStatus::Dirty, $room->fresh()->status);
    }
}
