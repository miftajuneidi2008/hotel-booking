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

        // One new key per new payment attempt: Chapa v2 requires it, and a retry of this same call is safe.
        $answer = $this->gateway->initialize($this->payload($booking, $tx, $by), 'hosted-'.$tx->tx_ref);
        $url = $answer['data']['checkout_url'] ?? null;

        if (($answer['status'] ?? null) !== 'success' || ! is_string($url) || $url === '') {
            $tx->update(['status' => ChapaStatus::Failed, 'note' => 'Could not start the payment.', 'response' => $answer]);

            Log::warning('Chapa: initialize failed.', ['tx_ref' => $tx->tx_ref, 'booking' => $booking->reference]);

            throw new BookingException(__('We could not start the payment. Please try again in a moment, or pay at the hotel.'));
        }

        // The last part of the checkout URL is Chapa's own payment reference.
        $tx->update([
            'checkout_url' => $url,
            'chapa_reference' => $this->text(basename((string) parse_url($url, PHP_URL_PATH)), 100),
        ]);

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
                'chapa_reference' => $this->text($data['chapa_reference'] ?? null, 100) ?? $fresh->chapa_reference,
                'channel' => $this->text($data['payment_method'] ?? null, 40),
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

        // Chapa does not take a payment below 100 cents (1.00).
        if ($this->toCents($booking->balanceDue()) < 100) {
            return __('The balance is too small to pay online. Please pay at the hotel.');
        }

        return null;
    }

    private function currency(): string
    {
        return (string) config('payments.currency', 'ETB');
    }

    /**
     * What Chapa's verify call tells us about the payment, judged against what we asked for.
     *
     * v2 shape: top-level status is "success" when the CALL worked (not when the guest paid); the payment
     * itself is data.status in UPPERCASE (SUCCESS, PENDING, FAILED, CANCELLED, INCOMPLETE, BLOCKED).
     * data.amount is in cents.
     *
     * @param  array<string, mixed>  $answer
     * @return array{0: 'paid'|'failed'|'pending'|'mismatch', 1: ?string}
     */
    private function assess(ChapaTransaction $tx, array $answer): array
    {
        $data = (array) ($answer['data'] ?? []);
        $remote = strtoupper((string) ($data['status'] ?? ''));

        if (($answer['status'] ?? null) === 'success' && $remote === 'SUCCESS') {
            $reference = (string) ($data['merchant_reference'] ?? '');

            if ($reference !== $tx->tx_ref && ! ($tx->chapa_reference !== null && (string) ($data['chapa_reference'] ?? '') === $tx->chapa_reference)) {
                return ['mismatch', 'Chapa answered for a different payment reference.'];
            }

            if (strtoupper((string) ($data['currency'] ?? '')) !== strtoupper($tx->currency)) {
                return ['mismatch', 'Chapa reports a different currency than the one requested.'];
            }

            // The guest may carry Chapa's fee, so paying more is fine; paying less is not. Only the
            // requested amount is ever put on the bill.
            if ((int) ($data['amount'] ?? 0) < $this->toCents((float) $tx->amount)) {
                return ['mismatch', 'Chapa reports a smaller amount than the one requested.'];
            }

            return ['paid', null];
        }

        if (in_array($remote, ['FAILED', 'CANCELLED', 'BLOCKED'], true)) {
            return ['failed', 'Chapa reports the payment as '.strtolower($remote).'.'];
        }

        // PENDING, INCOMPLETE, AUTH_NEEDED, "payment not found yet", ...: ask again later. The guest can
        // still finish on the same checkout page, and a fresh attempt is always possible.
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
     * The body for POST /v2/payments/hosted.
     *
     * @return array<string, mixed>
     */
    private function payload(Booking $booking, ChapaTransaction $tx, User $by): array
    {
        $name = preg_split('/\s+/', trim((string) $booking->guest_name), 2) ?: [];

        $customer = [
            'email' => $booking->guest_email ?: $by->email,
            'first_name' => $name[0] ?? '',
            'last_name' => $name[1] ?? '',
        ];

        // Ethiopian mobile numbers (09... or 07...) are sent as 2519xxxxxxxx / 2517xxxxxxxx; anything else is left out.
        $digits = preg_replace('/\D+/', '', (string) $booking->guest_phone) ?? '';
        $nine = substr($digits, -9);

        if (strlen($digits) >= 9 && preg_match('/^[79]\d{8}$/', $nine) === 1) {
            $customer['phone_number'] = '251'.$nine;
        }

        $payload = [
            'amount' => $this->toCents((float) $tx->amount),
            'currency' => $tx->currency,
            'merchant_reference' => $tx->tx_ref, // max 20 characters, unique
            'customer' => array_filter($customer, fn ($value): bool => $value !== ''),
            'meta' => ['booking' => $booking->reference],
            'customization' => [
                'title' => 'Hotel booking',
                'description' => 'Booking '.$booking->reference,
            ],
        ];

        // Chapa accepts HTTPS URLs only, so on plain-http local development they are left out (the guest
        // then stays on Chapa's page and the webhook / "Check payment status" still settle the payment).
        $callback = route('chapa.callback');
        $return = route('chapa.return', $tx->tx_ref);

        if (str_starts_with($callback, 'https://')) {
            $payload['callback_url'] = $callback;
            $payload['return_url'] = $return;
        }

        return $payload;
    }

    /** Chapa v2 amounts are integer cents: 100.00 ETB is 10000. */
    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function text(mixed $value, int $max): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? Str::limit((string) $value, $max, '') : null;
    }
}
