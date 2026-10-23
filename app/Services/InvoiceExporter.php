<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\PropertyTime;

/**
 * FR-35: one clean, stable structure for an invoice, ready to be mapped to the tax authority's format
 * when the property's e-invoicing is approved. Nothing is sent anywhere: the system has no official
 * e-invoicing accreditation, so this is prepared data only.
 *
 * The structure follows our own names ("schema") and carries every field the authority is likely to ask
 * for: seller and buyer TINs, numbered lines, VAT and service charge with their rates, and the date.
 */
class InvoiceExporter
{
    public const SCHEMA = 'hotel-invoice/1';

    /** @return array<string, mixed> */
    public function toArray(Invoice $invoice): array
    {
        $invoice->loadMissing('lines');

        return [
            'schema' => self::SCHEMA,
            'invoice' => [
                'number' => $invoice->number,
                'sequence' => $invoice->sequence,
                'status' => $invoice->status->value,
                'issued_at' => PropertyTime::local($invoice->issued_at)->toIso8601String(),
                'currency' => $invoice->currency,
                'voided_at' => $invoice->voided_at ? PropertyTime::local($invoice->voided_at)->toIso8601String() : null,
                'void_reason' => $invoice->void_reason,
            ],
            'seller' => [
                'name' => $invoice->seller_name,
                'tin' => $invoice->seller_tin,
                'address' => $invoice->seller_address,
                'phone' => $invoice->seller_phone,
                'email' => $invoice->seller_email,
            ],
            'buyer' => [
                'name' => $invoice->customer_name,
                'tin' => $invoice->customer_tin,
                'phone' => $invoice->customer_phone,
                'email' => $invoice->customer_email,
            ],
            'stay' => [
                'booking_reference' => $invoice->booking_reference,
                'room' => $invoice->room_label,
                'check_in' => $invoice->stay_check_in->toDateString(),
                'check_out' => $invoice->stay_check_out->toDateString(),
                'nights' => $invoice->nights,
                'guests' => $invoice->guests,
            ],
            'lines' => $invoice->lines->map(fn ($line): array => [
                'line_no' => $line->line_no,
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
                'taxable_subtotal' => (float) $invoice->subtotal_amount,
            ],
            'tax' => [
                'mode' => $invoice->tax_mode,
                'service_charge' => ['rate' => (float) $invoice->service_charge_rate, 'amount' => (float) $invoice->service_charge_amount],
                'vat' => [
                    'rate' => (float) $invoice->vat_rate,
                    'amount' => (float) $invoice->vat_amount,
                    'applied_on_service_charge' => $invoice->vat_includes_service_charge,
                ],
            ],
        ];
    }
}
