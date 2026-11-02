<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Notification;

/**
 * Tells the restaurant cashier that a hotel guest's order was entered at the front desk: who ordered,
 * from which room, at which table, and what.
 */
class RestaurantOrderNotification extends Notification
{
    /** @param  list<string>  $items  e.g. "2 x Pizza" */
    public function __construct(
        private readonly Booking $booking,
        private readonly ?string $table,
        private readonly array $items,
        private readonly string $enteredBy,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $params = [
            'guest' => $this->booking->guest_name,
            'room' => $this->booking->room->number,
            'table' => $this->table,
            'items' => implode(', ', $this->items),
            'by' => $this->enteredBy,
        ];

        return [
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->reference,
            'room' => $this->booking->room->number,
            'guest_name' => $this->booking->guest_name,
            'table_number' => $this->table,
            'items' => $this->items,
            'message' => $this->table !== null
                ? __('New order from the front desk: :guest (Room :room), table :table. :items. Entered by :by.', $params)
                : __('New order from the front desk: :guest (Room :room). :items. Entered by :by.', $params),
            'url' => route('staff.folio.show', $this->booking, false),
        ];
    }
}
