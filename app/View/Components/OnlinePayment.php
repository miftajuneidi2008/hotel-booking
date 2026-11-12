<?php

namespace App\View\Components;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\ChapaStatus;
use App\Models\Booking;
use App\Services\OnlinePaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The "Pay online" box on the guest's own booking page. It shows nothing for desk, phone or agent
 * bookings, for other people's bookings, or when Chapa is not configured.
 */
class OnlinePayment extends Component
{
    public function __construct(public Booking $booking) {}

    public function render(): View
    {
        $online = app(OnlinePaymentService::class);
        $user = auth()->user();

        if (! $online->enabled()
            || $user === null
            || $this->booking->source !== BookingSource::Online
            || $this->booking->user_id !== $user->id) {
            return $this->nothing();
        }

        $latest = $online->latest($this->booking);
        $balance = $this->booking->balanceDue();
        $paid = $this->booking->amountPaid();
        $canPay = $online->canPay($this->booking);
        $waiting = $latest?->status === ChapaStatus::Pending && $latest->created_at->gt(now()->subDay());

        $state = match (true) {
            $latest?->status === ChapaStatus::Review => 'review',
            $waiting && $canPay => 'pending',
            $canPay => 'pay',
            $this->booking->status !== BookingStatus::Cancelled && $paid > 0 && $balance <= 0.004 => 'paid',
            default => null,
        };

        if ($state === null) {
            return $this->nothing();
        }

        return view('components.online-payment', [
            'state' => $state,
            'balance' => $balance,
            'paid' => $paid,
            'latest' => $latest,
            'canPay' => $canPay,
            'currency' => (string) config('payments.currency', 'ETB'),
        ]);
    }

    /** The view renders nothing when the box does not apply. */
    private function nothing(): View
    {
        return view('components.online-payment', ['state' => null]);
    }
}
