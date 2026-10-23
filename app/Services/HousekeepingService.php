<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\IssuePriority;
use App\Enums\RoomStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\MaintenanceIssue;
use App\Models\Room;
use App\Models\RoomStatusChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Housekeeping, section 5.6: room status (clean, dirty, occupied, out of service) and maintenance notes.
 *
 * Occupied and dirty-after-check-out are set by the stay screens (section 5.3). This service handles what
 * housekeeping does: mark rooms clean or dirty, take a room out of service because of a problem, and put it
 * back when the problem is fixed. The room is locked inside a transaction, like StayService does, so a check-in
 * and a status change cannot cross each other.
 */
class HousekeepingService
{
    /**
     * Mark a room clean or dirty.
     *
     * Out of service rooms come back through "mark fixed" on their maintenance issue. Only a room that was set
     * out of service by hand (no open issue) can be put back here.
     *
     * @throws BookingException
     */
    public function changeStatus(Room $room, RoomStatus $to, User $by, ?string $note = null): Room
    {
        return DB::transaction(function () use ($room, $to, $by, $note): Room {
            $fresh = $this->lock($room->id);
            $from = $fresh->status;

            if ($to === RoomStatus::Occupied) {
                throw new BookingException('A room becomes occupied when a guest is checked in.');
            }

            if ($to === RoomStatus::OutOfService) {
                throw new BookingException('To take a room out of service, report a maintenance issue and tick "room cannot be used".');
            }

            if ($from === RoomStatus::Occupied || $this->hasGuestInHouse($fresh)) {
                throw new BookingException("A guest is in room {$fresh->number}. Its status changes when the guest checks out.");
            }

            if ($from === $to) {
                throw new BookingException("Room {$fresh->number} is already {$to->label()}.");
            }

            if ($from === RoomStatus::OutOfService && $this->openBlockingIssues($fresh)->exists()) {
                throw new BookingException("Room {$fresh->number} has an open maintenance issue. Mark it fixed to put the room back in service.");
            }

            $this->record($fresh, $from, $to, $by, $note);

            return $fresh;
        });
    }

    /**
     * Write down a maintenance problem. With $blocksRoom the room is also taken out of service, so it cannot be
     * sold or checked into until the issue is fixed.
     *
     * @param  array{description: string, priority?: ?string}  $data
     *
     * @throws BookingException
     */
    public function reportIssue(Room $room, array $data, bool $blocksRoom, User $by): MaintenanceIssue
    {
        return DB::transaction(function () use ($room, $data, $blocksRoom, $by): MaintenanceIssue {
            $fresh = $this->lock($room->id);
            $description = trim((string) $data['description']);

            if ($description === '') {
                throw new BookingException('Describe the problem.');
            }

            if ($blocksRoom && ($fresh->status === RoomStatus::Occupied || $this->hasGuestInHouse($fresh))) {
                throw new BookingException("A guest is in room {$fresh->number}, so it cannot be taken out of service now. Report the issue without blocking the room, or move the guest first.");
            }

            $issue = MaintenanceIssue::create([
                'room_id' => $fresh->id,
                'description' => $description,
                'priority' => $data['priority'] ?? IssuePriority::Normal->value,
                'blocks_room' => $blocksRoom,
                'reported_by' => $by->id,
            ]);

            if ($blocksRoom && $fresh->status !== RoomStatus::OutOfService) {
                $this->record($fresh, $fresh->status, RoomStatus::OutOfService, $by, $description);
            }

            return $issue;
        });
    }

    /**
     * Mark a problem as fixed. When it was the last issue keeping the room out of service, the room goes back
     * to "dirty", because it needs a clean before the next guest.
     *
     * @throws BookingException
     */
    public function resolveIssue(MaintenanceIssue $issue, ?string $note, User $by): MaintenanceIssue
    {
        return DB::transaction(function () use ($issue, $note, $by): MaintenanceIssue {
            $room = $this->lock($issue->room_id);
            $fresh = MaintenanceIssue::query()->whereKey($issue->id)->lockForUpdate()->firstOrFail();

            if ($fresh->isResolved()) {
                throw new BookingException('This issue is already fixed.');
            }

            $note = trim((string) $note);

            $fresh->update([
                'resolved_at' => now(),
                'resolved_by' => $by->id,
                'resolution_note' => $note !== '' ? $note : null,
            ]);

            if ($fresh->blocks_room
                && $room->status === RoomStatus::OutOfService
                && ! $this->openBlockingIssues($room)->exists()) {
                $this->record($room, RoomStatus::OutOfService, RoomStatus::Dirty, $by, 'Back in service: '.$fresh->description);
            }

            return $fresh;
        });
    }

    /** Confirmed bookings of this room that start within the next days, so the desk knows who is affected. */
    public function upcomingBookings(Room $room, int $days = 7): int
    {
        return Booking::query()
            ->where('room_id', $room->id)
            ->where('status', BookingStatus::Confirmed->value)
            ->whereDate('check_in', '<=', today()->addDays($days)->toDateString())
            ->whereDate('check_out', '>', today()->toDateString())
            ->count();
    }

    // ------------------------------------------------------------------

    private function lock(int $roomId): Room
    {
        return Room::query()->whereKey($roomId)->lockForUpdate()->firstOrFail();
    }

    private function hasGuestInHouse(Room $room): bool
    {
        return Booking::query()
            ->where('room_id', $room->id)
            ->where('status', BookingStatus::CheckedIn->value)
            ->exists();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<MaintenanceIssue> */
    private function openBlockingIssues(Room $room)
    {
        return MaintenanceIssue::query()
            ->where('room_id', $room->id)
            ->where('blocks_room', true)
            ->whereNull('resolved_at');
    }

    private function record(Room $room, RoomStatus $from, RoomStatus $to, User $by, ?string $note): void
    {
        $room->update(['status' => $to]);

        RoomStatusChange::create([
            'room_id' => $room->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null,
            'changed_by' => $by->id,
        ]);
    }
}
