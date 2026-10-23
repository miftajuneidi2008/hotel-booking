<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Support\PropertyTime;
use App\Support\TaxCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Invoices, section 5.9.
 *
 * - FR-33 the business TIN, the customer TIN (optional), VAT and service charge are stored on every invoice.
 * - FR-34 numbers run one after another and are never used twice, even for a voided invoice.
 * - FR-35 the data is kept as separate rows (invoices + invoice_lines) that InvoiceExporter turns into
 *   one clean structure.
 *
 * An invoice is issued after check-out, when the bill is closed, and is a frozen copy of that bill
 * (business rule 2). A wrong invoice is voided with a reason and replaced by a new one.
 */
class InvoiceService
{
    public function __construct(private readonly FolioService $folio) {}

    /** The seller details printed on an invoice. Name, address, phone and email fall back to config/hotel.php. */
    public function seller(): array
    {
        $business = (array) config('tax.business');

        return [
            'name' => $business['name'] ?: (string) config('hotel.name', config('app.name')),
            'tin' => trim((string) ($business['tin'] ?? '')),
            'address' => $business['address'] ?: config('hotel.contact.address'),
            'phone' => $business['phone'] ?: config('hotel.contact.phone'),
            'email' => $business['email'] ?: config('hotel.contact.email'),
        ];
    }

    /** The invoice that counts for this booking, if one has been issued. */
    public function activeFor(Booking $booking): ?Invoice
    {
        return Invoice::query()
            ->where('booking_id', $booking->id)
            ->where('status', InvoiceStatus::Issued->value)
            ->latest('id')
            ->first();
    }

    /** Why an invoice cannot be issued for this booking right now, or null when it can. */
    public function issueBlocker(Booking $booking): ?string
    {
        if ($booking->status !== BookingStatus::CheckedOut) {
            return 'An invoice can be issued after the guest has checked out, when the bill is closed.';
        }

        if ($this->seller()['tin'] === '') {
            return 'The business TIN is not set. Set TAX_BUSINESS_TIN in the .env file (or config/tax.php) before issuing invoices.';
        }

        if (($existing = $this->activeFor($booking)) !== null) {
            return 'This booking already has invoice '.$existing->number.'. Void it first if it has to be replaced.';
        }

        return null;
    }

    /**
     * The document as it would be issued right now (used for the draft on screen), in the same shape
     * as document() so one view prints both.
     *
     * @return array<string, mixed>
     */
    public function draft(Booking $booking): array
    {
        $booking->loadMissing('room.roomType');
        $folio = $this->folio->summary($booking);

        $lines = [];

        foreach ($folio['room_lines'] as $line) {
            $lines[] = [
                'type' => InvoiceLine::ROOM,
                'category' => null,
                'description' => 'Room '.$booking->room->number.' - '.Carbon::parse($line['night'])->format('D, M j, Y'),
                'quantity' => 1,
                'unit_price' => $line['amount'],
                'amount' => $line['amount'],
            ];
        }

        foreach ($folio['charges']->whereNull('voided_at') as $charge) {
            $lines[] = [
                'type' => InvoiceLine::CHARGE,
                'category' => $charge->category->value,
                'description' => $charge->description.' ('.$charge->category->label().')',
                'quantity' => (int) $charge->quantity,
                'unit_price' => (float) $charge->unit_price,
                'amount' => (float) $charge->amount,
            ];
        }

        foreach ($folio['discounts']->whereNull('voided_at') as $discount) {
            $lines[] = [
                'type' => InvoiceLine::DISCOUNT,
                'category' => null,
                'description' => 'Discount - '.$discount->reason,
                'quantity' => 1,
                'unit_price' => -(float) $discount->amount,
                'amount' => -(float) $discount->amount,
            ];
        }

        $tax = TaxCalculator::fromConfig()->breakdown($folio['net']);

        return [
            'number' => null,
            'status' => 'draft',
            'issued_at' => null,
            'issued_by' => null,
            'seller' => $this->seller(),
            'customer' => [
                'name' => $booking->guest_name,
                'tin' => null,
                'phone' => $booking->guest_phone,
                'email' => $booking->guest_email,
            ],
            'stay' => [
                'reference' => $booking->reference,
                'room' => 'Room '.$booking->room->number.' ('.$booking->room->roomType->name.')',
                'check_in' => $booking->check_in,
                'check_out' => $booking->check_out,
                'nights' => $booking->nights(),
                'guests' => (int) $booking->guests,
            ],
            'currency' => (string) config('payments.currency', 'ETB'),
            'lines' => $lines,
            'totals' => [
                'gross' => $folio['gross'],
                'discounts' => $folio['discounts_total'],
                'total' => $folio['net'],
                'tax_mode' => 'inclusive',
                'subtotal' => $tax['subtotal'],
                'service_rate' => $tax['service_rate'],
                'service_charge' => $tax['service_charge'],
                'vat_rate' => $tax['vat_rate'],
                'vat' => $tax['vat'],
                'vat_includes_service_charge' => $tax['vat_includes_service_charge'],
            ],
            'paid' => $folio['paid'],
            'balance' => $folio['balance'],
            'void' => null,
        ];
    }

    /**
     * An issued invoice as a document. Everything comes from the stored copy, never from today's
     * prices, rates or settings.
     *
     * @return array<string, mixed>
     */
    public function document(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines', 'issuer', 'voider']);

        return [
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'issued_at' => PropertyTime::local($invoice->issued_at),
            'issued_by' => $invoice->issuer?->name,
            'seller' => [
                'name' => $invoice->seller_name,
                'tin' => $invoice->seller_tin,
                'address' => $invoice->seller_address,
                'phone' => $invoice->seller_phone,
                'email' => $invoice->seller_email,
            ],
            'customer' => [
                'name' => $invoice->customer_name,
                'tin' => $invoice->customer_tin,
                'phone' => $invoice->customer_phone,
                'email' => $invoice->customer_email,
            ],
            'stay' => [
                'reference' => $invoice->booking_reference,
                'room' => $invoice->room_label,
                'check_in' => $invoice->stay_check_in,
                'check_out' => $invoice->stay_check_out,
                'nights' => $invoice->nights,
                'guests' => $invoice->guests,
            ],
            'currency' => $invoice->currency,
            'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => [
                'type' => $line->type,
                'category' => $line->category,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'amount' => (float) $line->amount,
            ])->all(),
            'totals' => [
                'gross' => (float) $invoice->gross_amount,
                'discounts' => (float) $invoice->discount_amount,
                'total' => (float) $invoice->total_amount,
                'tax_mode' => $invoice->tax_mode,
                'subtotal' => (float) $invoice->subtotal_amount,
                'service_rate' => (float) $invoice->service_charge_rate,
                'service_charge' => (float) $invoice->service_charge_amount,
                'vat_rate' => (float) $invoice->vat_rate,
                'vat' => (float) $invoice->vat_amount,
                'vat_includes_service_charge' => $invoice->vat_includes_service_charge,
            ],
            'paid' => (float) $invoice->amount_paid,
            'balance' => (float) $invoice->balance_due,
            'void' => $invoice->isVoided() ? [
                'at' => $invoice->voided_at ? PropertyTime::local($invoice->voided_at) : null,
                'by' => $invoice->voider?->name,
                'reason' => $invoice->void_reason,
            ] : null,
        ];
    }

    /**
     * Issue the invoice for a checked-out booking. The booking row is locked, so two people clicking
     * at the same moment cannot issue two invoices, and the number is taken inside the same
     * transaction, so a failure leaves no gap in the numbers.
     *
     * @throws BookingException
     */
    public function issue(Booking $booking, User $by, ?string $customerName = null, ?string $customerTin = null): Invoice
    {
        return DB::transaction(function () use ($booking, $by, $customerName, $customerTin): Invoice {
            $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (($blocker = $this->issueBlocker($fresh)) !== null) {
                throw new BookingException($blocker);
            }

            $doc = $this->draft($fresh);
            $totals = $doc['totals'];

            $name = trim((string) $customerName);
            $tin = trim((string) $customerTin);

            [$sequence, $number] = $this->nextNumber();

            $invoice = Invoice::create([
                'sequence' => $sequence,
                'number' => $number,
                'booking_id' => $fresh->id,
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'issued_by' => $by->id,

                'seller_name' => $doc['seller']['name'],
                'seller_tin' => $doc['seller']['tin'],
                'seller_address' => $doc['seller']['address'],
                'seller_phone' => $doc['seller']['phone'],
                'seller_email' => $doc['seller']['email'],

                'customer_name' => $name !== '' ? $name : $doc['customer']['name'],
                'customer_tin' => $tin !== '' ? $tin : null,
                'customer_phone' => $doc['customer']['phone'],
                'customer_email' => $doc['customer']['email'],

                'booking_reference' => $doc['stay']['reference'],
                'room_label' => $doc['stay']['room'],
                'stay_check_in' => $doc['stay']['check_in']->toDateString(),
                'stay_check_out' => $doc['stay']['check_out']->toDateString(),
                'nights' => $doc['stay']['nights'],
                'guests' => $doc['stay']['guests'],

                'currency' => $doc['currency'],
                'gross_amount' => $totals['gross'],
                'discount_amount' => $totals['discounts'],
                'total_amount' => $totals['total'],

                'tax_mode' => $totals['tax_mode'],
                'subtotal_amount' => $totals['subtotal'],
                'service_charge_rate' => $totals['service_rate'],
                'service_charge_amount' => $totals['service_charge'],
                'vat_rate' => $totals['vat_rate'],
                'vat_amount' => $totals['vat'],
                'vat_includes_service_charge' => $totals['vat_includes_service_charge'],

                'amount_paid' => $doc['paid'],
                'balance_due' => $doc['balance'],
            ]);

            foreach ($doc['lines'] as $i => $line) {
                $invoice->lines()->create($line + ['line_no' => $i + 1]);
            }

            return $invoice;
        });
    }

    /**
     * Void an invoice with a reason. The number stays used. Manager and owner only (InvoicePolicy).
     *
     * @throws BookingException
     */
    public function void(Invoice $invoice, string $reason, User $by): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $by): Invoice {
            $fresh = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($fresh->isVoided()) {
                throw new BookingException('This invoice is already voided.');
            }

            $reason = trim($reason);

            if ($reason === '') {
                throw new BookingException('Enter the reason for voiding this invoice.');
            }

            $fresh->update([
                'status' => InvoiceStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => $reason,
            ]);

            return $fresh;
        });
    }

    /**
     * Take the next number. The counter row is locked until the transaction ends.
     *
     * @return array{0: int, 1: string}
     */
    private function nextNumber(): array
    {
        $row = DB::table('invoice_counters')->where('name', 'invoice')->lockForUpdate()->first();

        if ($row === null) {
            DB::table('invoice_counters')->insert(['name' => 'invoice', 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('invoice_counters')->where('name', 'invoice')->lockForUpdate()->first();
        }

        $sequence = (int) $row->last_number + 1;

        DB::table('invoice_counters')->where('name', 'invoice')->update(['last_number' => $sequence, 'updated_at' => now()]);

        $number = (string) config('tax.invoice.prefix', 'INV-')
            .str_pad((string) $sequence, max(1, (int) config('tax.invoice.padding', 6)), '0', STR_PAD_LEFT);

        return [$sequence, $number];
    }
}
