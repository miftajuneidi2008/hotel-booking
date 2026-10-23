<?php

namespace App\View\Components;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\CashSummaryService;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

/**
 * The payments box on the guest bill page: what has been paid, plus the forms to take a payment,
 * pay a refund or void a wrong entry.
 */
class PaymentsPanel extends Component
{
    public function __construct(public Booking $booking) {}

    public function render(): View
    {
        $payments = $this->booking->payments()->with(['creator', 'voider'])->orderBy('id')->get();
        $balance = $this->booking->balanceDue();
        $paid = $this->booking->amountPaid();

        return view('components.payments-panel', [
            'payments' => $payments,
            'balance' => $balance,
            'paid' => $paid,
            'methods' => PaymentMethod::cases(),
            'currencies' => app(PaymentService::class)->currencies(),
            'baseCurrency' => (string) config('payments.currency', 'ETB'),
            'timezone' => app(CashSummaryService::class)->timezone(),
            'canRecord' => Gate::allows('create', Payment::class) && $this->booking->status !== BookingStatus::Cancelled && $balance > 0,
            'canRefund' => Gate::allows('create', Payment::class) && $paid > 0,
            'canVoid' => Gate::allows('void', new Payment),
            'refundLimit' => config('payments.refund_approval_amount'),
        ]);
    }
}
