<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\FolioCharge;
use App\Models\User;
use App\Notifications\RestaurantOrderNotification;
use Illuminate\Support\Collection;
use Throwable;

/**
 * When the front desk (receptionist, manager or owner) puts a food or drink order on a guest's room bill, the
 * restaurant cashiers get a notification with the guest, the room, the table number and the items.
 * An order the cashier enters does not notify anyone, because the cashier already knows about it.
 */
class RestaurantOrderAlertService
{
    /** @param  Collection<int, FolioCharge>  $charges */
    public function roomOrder(Booking $booking, ?string $table, Collection $charges, User $by): void
    {
        if ($by->role === UserRole::Cashier || $charges->isEmpty()) {
            return;
        }

        $booking->loadMissing('room');

        $items = $charges
            ->map(fn (FolioCharge $charge): string => $charge->quantity.' x '.$charge->description)
            ->values()
            ->all();

        foreach ($this->cashiers($by) as $cashier) {
            try {
                $cashier->notify(new RestaurantOrderNotification($booking, $table, $items, $by->name));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** @return Collection<int, User> */
    private function cashiers(User $except): Collection
    {
        return User::query()
            ->where('role', UserRole::Cashier->value)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('id', '!=', $except->id)
            ->get();
    }
}
