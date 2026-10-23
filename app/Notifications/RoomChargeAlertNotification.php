<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Notification;

/**
 * Tells the front desk that restaurant charges pushed a guest's unpaid balance over the alert limit,
 * so nothing surprises them at check-out.
 */
class RoomChargeAlertNotification extends Notification
{
    public function __construct(
        private readonly Booking $booking,
        private readonly float $added,
        private readonly float $balance,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->reference,
            'room' => $this->booking->room->number,
            'guest_name' => $this->booking->guest_name,
            'message' => __('Room :room (:guest): ETB :added was added to the room bill. The unpaid balance is now ETB :balance.', [
                'room' => $this->booking->room->number,
                'guest' => $this->booking->guest_name,
                'added' => number_format($this->added, 2),
                'balance' => number_format($this->balance, 2),
            ]),
            'url' => route('staff.folio.show', $this->booking, false),
        ];
    }
}
