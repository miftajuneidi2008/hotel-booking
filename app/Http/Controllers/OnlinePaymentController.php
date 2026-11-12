<?php

namespace App\Http\Controllers;

use App\Enums\ChapaStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Services\OnlinePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pay a website booking through Chapa: start the payment, handle the guest coming back, and receive
 * Chapa's callback and webhook. Nothing here trusts what the browser or the webhook says: every path
 * ends in OnlinePaymentService::confirm(), which asks Chapa directly.
 */
class OnlinePaymentController extends Controller
{
    public function __construct(private readonly OnlinePaymentService $online) {}

    /** The guest pressed "Pay online": create the payment and send them to Chapa. */
    public function start(Request $request, Booking $booking): RedirectResponse
    {
        // Only the guest who made the booking pays for it here. Staff take payments on the guest bill.
        abort_unless($booking->user_id !== null && $booking->user_id === $request->user()->id, 403);

        try {
            $url = $this->online->start($booking, $request->user());
        } catch (BookingException $e) {
            return redirect()->route('my-bookings.show', $booking)->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }

    /**
     * The guest's browser comes back from Chapa (also used by the "Check payment status" link).
     * The payment is verified with Chapa before anything is said about it.
     */
    public function return(string $txRef): RedirectResponse
    {
        $tx = $this->online->confirm($txRef);

        abort_if($tx === null, 404);

        $back = redirect()->route('my-bookings.show', $tx->booking_id);

        return match ($tx->status) {
            ChapaStatus::Success => $back->with('status', __('Payment received. Thank you!')),
            ChapaStatus::Review => $back->with('error', __('We received your payment, but the hotel needs to check it. Please contact reception and give them your booking reference.')),
            ChapaStatus::Failed => $back->with('error', __('The payment was not completed. You can try again.')),
            ChapaStatus::Pending => $back->with('status', __('We are waiting for Chapa to confirm your payment. If you have paid, check again in a minute.')),
        };
    }

    /** Chapa's callback_url: a GET with trx_ref, ref_id and status once a payment finishes. */
    public function callback(Request $request): JsonResponse
    {
        $txRef = $request->query('trx_ref', $request->query('tx_ref'));

        if (is_string($txRef) && $txRef !== '') {
            $this->online->confirm($txRef);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Chapa's webhook: a POST for every payment event. It covers the guest who pays and then closes
     * the browser before coming back.
     */
    public function webhook(Request $request): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $txRef = $request->input('tx_ref', $request->input('trx_ref'));

        if (! is_string($txRef) || $txRef === '') {
            return response()->json(['ok' => true]);
        }

        $tx = $this->online->confirm($txRef);

        // Chapa says "paid" but we could not confirm it yet (Chapa unreachable): answer with an error
        // so that Chapa sends the event again, instead of the payment being forgotten.
        if ($tx?->status === ChapaStatus::Pending && $request->input('event') === 'charge.success') {
            return response()->json(['message' => 'Not confirmed yet, please retry.'], 503);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Chapa signs webhooks with HMAC SHA256 and sends the result in x-chapa-signature (signature of the
     * body) and chapa-signature. Chapa's guide says "your secret key" for the signing key, so the webhook
     * secret hash and the API secret key are both accepted.
     *
     * With no webhook secret configured every call is accepted. That is safe, because confirm() never
     * believes the webhook: it asks Chapa. A forged call can only cause one extra verify request.
     */
    private function signatureIsValid(Request $request): bool
    {
        if (blank(config('chapa.webhook_secret'))) {
            return true;
        }

        $body = $request->getContent();
        $received = array_filter([
            (string) $request->header('x-chapa-signature', ''),
            (string) $request->header('chapa-signature', ''),
        ]);

        foreach (array_filter([config('chapa.webhook_secret'), config('chapa.secret_key')]) as $secret) {
            $secret = (string) $secret;
            $valid = [hash_hmac('sha256', $body, $secret), hash_hmac('sha256', $secret, $secret)];

            foreach ($received as $signature) {
                foreach ($valid as $candidate) {
                    if (hash_equals($candidate, $signature)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
