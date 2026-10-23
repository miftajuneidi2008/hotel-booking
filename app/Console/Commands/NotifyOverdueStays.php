<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\OverdueStayNotification;
use App\Support\PropertyTime;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Tells reception (owner, manager, receptionist) and the guest's own account about checked-in guests
 * who stayed past their check-out date. A guest is announced once per day while still overdue.
 * The room stays held for them meanwhile (Booking::scopeOverlapping).
 *
 * Scheduled every morning in routes/console.php. Can also be run by hand: php artisan hotel:notify-overdue
 */
class NotifyOverdueStays extends Command
{
    protected $signature = 'hotel:notify-overdue';

    protected $description = 'Notify reception and the guest about guests who stayed past their check-out date';

    public function handle(): int
    {
        $today = PropertyTime::today();

        $overdue = Booking::query()
            ->with(['room', 'user'])
            ->where('status', BookingStatus::CheckedIn->value)
            ->whereDate('check_out', '<', $today)
            ->where(fn (Builder $query) => $query
                ->whereNull('overdue_notified_on')
                ->orWhereDate('overdue_notified_on', '<', $today))
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue guests to announce.');

            return self::SUCCESS;
        }

        $staff = User::query()
            ->whereIn('role', array_map(
                fn (UserRole $role): string => $role->value,
                [UserRole::Owner, UserRole::Manager, UserRole::Receptionist],
            ))
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get();

        foreach ($overdue as $booking) {
            foreach ($staff as $user) {
                $this->send($user, new OverdueStayNotification($booking, forStaff: true));
            }

            // The guest's own account (online bookings). Desk guests without an account have none.
            $guest = $booking->user;

            if ($guest !== null && $guest->deleted_at === null && $guest->is_active !== false
                && ! $staff->contains('id', $guest->id)) {
                $this->send($guest, new OverdueStayNotification($booking, forStaff: false));
            }

            Booking::query()->whereKey($booking->id)->update(['overdue_notified_on' => $today]);
        }

        $this->info("Announced {$overdue->count()} overdue guest(s).");

        return self::SUCCESS;
    }

    private function send(User $user, OverdueStayNotification $notification): void
    {
        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            // One failed message (for example the mail server is down) must not stop the others.
            report($e);
        }
    }
}
