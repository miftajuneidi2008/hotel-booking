<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\StoreRefundRequest;
use App\Http\Requests\VoidPaymentRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;

/**
 * Take a payment, pay a refund, or void a wrong entry on a guest bill (FR-20, FR-21, FR-22).
 * The forms live on the guest bill page.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function store(StorePaymentRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $payment = $this->payments->record($booking, $request->validated(), $request->user());
        } catch (BookingException $e) {
            return $this->back($booking, 'payment')->with('error', $e->getMessage());
        }

        return $this->back($booking)->with('status', "Payment {$payment->number} recorded: ETB ".number_format((float) $payment->amount, 2).'.');
    }

    public function refund(StoreRefundRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $refund = $this->payments->refund($booking, $request->validated(), $request->user());
        } catch (BookingException $e) {
            return $this->back($booking, 'refund')->with('error', $e->getMessage());
        }

        return $this->back($booking)->with('status', "Refund {$refund->number} recorded: ETB ".number_format((float) $refund->amount, 2).'.');
    }

    public function void(VoidPaymentRequest $request, Payment $payment): RedirectResponse
    {
        try {
            $this->payments->void($payment, $request->validated('reason'), $request->user());
        } catch (BookingException $e) {
            return $this->back($payment->booking)->with('error', $e->getMessage());
        }

        return $this->back($payment->booking)->with('status', "{$payment->number} was voided.");
    }

    /** Back to the guest bill. On an error the typed values stay in the form that was used. */
    private function back(Booking $booking, ?string $form = null): RedirectResponse
    {
        $redirect = redirect()->route('staff.folio.show', $booking);

        return $form ? $redirect->withInput()->with('payment_form', $form) : $redirect;
    }
}
