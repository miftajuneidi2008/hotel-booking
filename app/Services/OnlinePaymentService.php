<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\ChapaStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\ChapaTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lets a guest who booked on the website pay the balance of that booking through Chapa.
 *
 * Desk, phone and agent bookings are not touched: staff record those payments by hand (PaymentService).
 *
 * The rule that keeps the books safe: a payment is put on the guest bill (a row in the payments table)
 * only after Chapa's verify call says it was paid, for the right amount and currency. The redirect back
 * from Chapa, Chapa's callback and Chapa's webhook all end up in confirm(), which can be called any
 * number of times for the same payment and posts it once.
 */
class OnlinePaymentService
{
    public function __construct(private readonly ChapaGateway $gateway) {}

    public function enabled(): bool
    {
        return $this->gateway->configured();
    }

    /** Can this booking be paid through Chapa right now? */
    public function canPay(Booking $booking): bool
    {
        return $this->enabled() && $this->unavailableReason($booking) === null;
    }

    /** The most recent attempt on this booking, if any. */
    public function latest(Booking $booking): ?ChapaTransaction
    {
        return ChapaTransaction::query()->where('booking_id', $booking->id)->latest('id')->first();
    }

    /**
     * Start a payment for the whole balance and return the Chapa page to send the guest to.
     *
     * @throws BookingException
     */
    public function start(Booking $booking, User $by): string
    {
        if (! $this->enabled()) {
            throw new BookingException(__('Online payment is not available right now. Please pay at the hotel.'));
        }

        // The guest may have paid on an earlier try and closed the page before we heard about it.
        // Settle those first, so the balance below is up to date and nobody pays twice.
        ChapaTransaction::query()
            ->where('booking_id', $booking->id)
            ->where('status', ChapaStatus::Pending->value)
            ->where('created_at', '>=', now()->subDay())
            ->latest('id')
            ->limit(3)
            ->get()
            ->each(fn (ChapaTransaction $tx) => $this->confirm($tx->tx_ref));

        $booking->refresh();

        if ($reason = $this->unavailableReason($booking)) {
            throw new BookingException($reason);
        }

        $amount = round($booking->balanceDue(), 2);

        $tx = ChapaTransaction::create([
            'booking_id' => $booking->id,
            'user_id' => $by->id,
            'tx_ref' => $booking->reference.'-'.Str::upper(Str::random(8)),
            'amount' => $amount,
            'currency' => $this->currency(),
            'status' => ChapaStatus::Pending,
        ]);

        $answer = $this->gateway->initialize($this->payload($booking, $tx, $by));
        $url = $answer['data']['checkout_url'] ?? null;

        if (($answer['status'] ?? null) !== 'success' || ! is_string($url) || $url === '') {
            $tx->update(['status' => ChapaStatus::Failed, 'note' => 'Could not start the payment.', 'response' => $answer]);

            Log::warning('Chapa: initialize failed.', ['tx_ref' => $tx->tx_ref, 'booking' => $booking->reference]);

            throw new BookingException(__('We could not start the payment. Please try again in a moment, or pay at the hotel.'));
        }

        $tx->update(['checkout_url' => $url]);

        return $url;
    }

    /**
     * Check a payment with Chapa and, if it was really paid, put it on the guest bill. Safe to call again
     * and again (return page, callback, webhook, "check status" button): a paid payment is posted once.
     *
     * Returns null for an unknown reference. When Chapa cannot be reached the attempt is returned as it
     * was, still pending, so the caller can ask the guest to try again shortly.
     */
    public function confirm(string $txRef): ?ChapaTransaction
    {
        $tx = ChapaTransaction::query()->where('tx_ref', $txRef)->first();

        if ($tx === null) {
            return null;
        }

        if ($tx->status->isSettled()) {
            return $tx;
        }

        // Ask Chapa before opening the database transaction, so no lock is held during the web request.
        $answer = $this->gateway->verify($tx->tx_ref);

        if ($answer === null) {
            return $tx;
        }

        [$outcome, $note] = $this->assess($tx, $answer);

        return DB::transaction(function () use ($tx, $answer, $outcome, $note): ChapaTransaction {
            // Re-read under a lock: the redirect and the webhook often arrive together.
            $fresh = ChapaTransaction::query()->whereKey($tx->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status->isSettled()) {
                return $fresh;
            }

            $data = (array) ($answer['data'] ?? []);
            $details = [
                'response' => $answer,
                'chapa_reference' => $this->text($data['reference'] ?? $data['ref_id'] ?? null, 100),
                'channel' => $this->text($data['payment_method'] ?? $data['method'] ?? null, 40),
            ];

            if ($outcome === 'pending') {
                return $fresh;
            }

            if ($outcome === 'failed') {
                $fresh->update($details + ['status' => ChapaStatus::Failed, 'note' => $note]);

                return $fresh;
            }

            if ($outcome === 'mismatch') {
                return $this->flagForReview($fresh, $details, $note);
            }

            // Paid. Lock the booking the same way PaymentService does, then check the bill can take it.
            $booking = Booking::withTrashed()->whereKey($fresh->booking_id)->lockForUpdate()->firstOrFail();

            if ($booking->status === BookingStatus::Cancelled) {
                return $this->flagForReview($fresh, $details, 'The booking was cancelled before this payment arrived. Refund the guest.');
            }

            if ((float) $fresh->amount > $booking->balanceDue() + 0.004) {
                return $this->flagForReview($fresh, $details, 'The guest paid more than the balance due (the bill was paid another way meanwhile). Refund the difference.');
            }

            // Recorded as a card payment with no staff user: the reference shows it came from Chapa, and
            // card money never counts as cash in the drawer. The exact channel (Telebirr, card, ...) is kept
            // on the chapa_transactions row.
            $payment = $booking->payments()->create([
                'kind' => PaymentKind::Payment,
                'method' => PaymentMethod::Card,
                'reference' => 'CHAPA-'.$fresh->tx_ref,
                'currency' => $fresh->currency,
                'original_amount' => $fresh->amount,
                'exchange_rate' => 1,
                'amount' => $fresh->amount,
                'created_by' => null,
                'paid_at' => now(),
            ]);

            $fresh->update($details + [
                'status' => ChapaStatus::Success,
                'payment_id' => $payment->id,
                'paid_at' => $payment->paid_at,
                'note' => null,
            ]);

            return $fresh;
        });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Why this booking cannot be paid online, or null when it can. */
    private function unavailableReason(Booking $booking): ?string
    {
        if ($booking->source !== BookingSource::Online) {
            return __('Online payment is only for bookings made on the website.');
        }

        if ($booking->status !== BookingStatus::Confirmed) {
            return __('This booking can no longer be paid online.');
        }

        if ($booking->balanceDue() <= 0.004) {
            return __('This booking is already paid in full.');
        }

        return null;
    }

    private function currency(): string
    {
        return (string) config('payments.currency', 'ETB');
    }

    /**
     * What Chapa tells us about the payment, judged against what we asked for.
     *
     * @param  array<string, mixed>  $answer
     * @return array{0: 'paid'|'failed'|'pending'|'mismatch', 1: ?string}
     */
    private function assess(ChapaTransaction $tx, array $answer): array
    {
        $data = (array) ($answer['data'] ?? []);
        $remote = strtolower((string) ($data['status'] ?? ''));

        if (($answer['status'] ?? null) === 'success' && $remote === 'success') {
            if (isset($data['tx_ref']) && (string) $data['tx_ref'] !== $tx->tx_ref) {
                return ['mismatch', 'Chapa answered for a different reference.'];
            }

            if (strtoupper((string) ($data['currency'] ?? '')) !== strtoupper($tx->currency)) {
                return ['mismatch', 'Chapa reports a different currency than the one requested.'];
            }

            // Paying more is fine (the guest may carry Chapa's fee); paying less is not. Only the
            // requested amount is ever put on the bill.
            if ((float) ($data['amount'] ?? 0) + 0.009 < (float) $tx->amount) {
                return ['mismatch', 'Chapa reports a smaller amount than the one requested.'];
            }

            // A test payment must never count as real money, and the other way round.
            if (isset($data['mode']) && strtolower((string) $data['mode']) !== ($this->gateway->isTestMode() ? 'test' : 'live')) {
                return ['mismatch', 'The payment mode (test or live) does not match the configured key.'];
            }

            return ['paid', null];
        }

        if (in_array($remote, ['failed', 'cancelled', 'canceled', 'reversed'], true)) {
            return ['failed', 'Chapa reports the payment as '.$remote.'.'];
        }

        // Anything else (still pending, "transaction not found yet", ...): ask again later.
        return ['pending', null];
    }

    /**
     * Money was taken but cannot go on the bill by itself. It is never lost: the row stays with a note,
     * and the error log has it for the hotel to follow up.
     *
     * @param  array<string, mixed>  $details
     */
    private function flagForReview(ChapaTransaction $tx, array $details, ?string $note): ChapaTransaction
    {
        $tx->update($details + ['status' => ChapaStatus::Review, 'note' => $note, 'paid_at' => now()]);

        Log::error('Chapa payment needs review.', ['tx_ref' => $tx->tx_ref, 'booking_id' => $tx->booking_id, 'note' => $note]);

        return $tx;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Booking $booking, ChapaTransaction $tx, User $by): array
    {
        $name = preg_split('/\s+/', trim((string) $booking->guest_name), 2) ?: [];

        $payload = [
            'amount' => number_format((float) $tx->amount, 2, '.', ''),
            'currency' => $tx->currency,
            'email' => $booking->guest_email ?: $by->email,
            'first_name' => $name[0] ?? '',
            'last_name' => $name[1] ?? '',
            'tx_ref' => $tx->tx_ref,
            // Chapa calls this (GET) when the payment finishes; the guest's browser comes back to return_url.
            'callback_url' => route('chapa.callback'),
            'return_url' => route('chapa.return', $tx->tx_ref),
            'customization' => [
                'title' => 'Hotel booking',
                'description' => 'Booking '.$booking->reference,
            ],
        ];

        // Chapa only accepts 09xxxxxxxx or 07xxxxxxxx, so a number in any other format is left out.
        $digits = preg_replace('/\D+/', '', (string) $booking->guest_phone) ?? '';
        $local = '0'.substr($digits, -9);

        if (strlen($digits) >= 9 && preg_match('/^0[79]\d{8}$/', $local) === 1) {
            $payload['phone_number'] = $local;
        }

        return array_filter($payload, fn ($value): bool => $value !== '');
    }

    private function text(mixed $value, int $max): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? Str::limit((string) $value, $max, '') : null;
    }
}
