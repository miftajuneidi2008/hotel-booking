<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\RoomChargeAlertNotification;
use Illuminate\Support\Collection;
use Throwable;

/**
 * When the restaurant puts an order on a room bill and the guest's unpaid balance goes over
 * config('folio.room_charge_alert_amount'), the front desk gets a notification. Only the order that
 * crosses the limit sends one. Set the limit to null to turn this off.
 */
class RoomChargeAlertService
{
    public function roomCharge(Booking $booking, float $added, User $by): void
    {
        $limit = config('folio.room_charge_alert_amount');

        if (! is_numeric($limit) || (float) $limit <= 0) {
            return;
        }

        $limit = (float) $limit;
        $after = $booking->balanceDue();
        $before = round($after - $added, 2);

        if ($after < $limit || $before >= $limit) {
            return;
        }

        $booking->loadMissing('room');

        foreach ($this->recipients($by) as $user) {
            try {
                $user->notify(new RoomChargeAlertNotification($booking, $added, $after));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** @return Collection<int, User> */
    private function recipients(User $except): Collection
    {
        return User::query()
            ->whereIn('role', array_map(
                fn (UserRole $role): string => $role->value,
                [UserRole::Owner, UserRole::Manager, UserRole::Receptionist],
            ))
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('id', '!=', $except->id)
            ->get();
    }
}
