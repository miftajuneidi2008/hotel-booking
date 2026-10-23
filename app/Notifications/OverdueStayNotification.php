<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\PropertyTime;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A checked-in guest stayed past the check-out date. Sent to reception ($forStaff = true) and to
 * the guest's own account. Not queued, so it works without a queue worker running.
 */
class OverdueStayNotification extends Notification
{
    public function __construct(
        private readonly Booking $booking,
        private readonly bool $forStaff,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // The guest also gets an email (it only reaches them when mail is set up in .env).
        return $this->forStaff ? ['database'] : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your stay has ended - please check out'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->message());
    }

    /**
     * Stored in the notifications table. The link is a path (no host), so it keeps working
     * whatever address the hotel's computer is opened with.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->reference,
            'room' => $this->booking->room->number,
            'guest_name' => $this->booking->guest_name,
            'check_out' => $this->booking->check_out->toDateString(),
            'days_overdue' => $this->daysOverdue(),
            'message' => $this->message(),
            'url' => $this->forStaff
                ? route('staff.bookings.show', $this->booking, false)
                : route('my-bookings.show', $this->booking, false),
        ];
    }

    private function daysOverdue(): int
    {
        $today = Carbon::parse(PropertyTime::today())->startOfDay();

        return max(1, (int) $this->booking->check_out->copy()->startOfDay()->diffInDays($today));
    }

    private function message(): string
    {
        $days = $this->daysOverdue();
        $replace = [
            'room' => $this->booking->room->number,
            'guest' => $this->booking->guest_name,
            'date' => $this->booking->check_out->format('M j'),
            'days' => $days === 1 ? __('1 day') : __(':n days', ['n' => $days]),
        ];

        if ($this->forStaff) {
            return __('Room :room: :guest should have left on :date (:days overdue). The room stays held for this guest until check-out.', $replace);
        }

        return __('Your stay in room :room ended on :date. Please check out at the front desk, or ask reception to extend your stay.', $replace);
    }
}
