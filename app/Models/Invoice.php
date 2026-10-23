<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * An issued invoice (section 5.9). It is a frozen copy of the guest bill: after it is created only the
 * void fields can change, and it can never be deleted (business rules 2 and 7, FR-34).
 */
#[Fillable([
    'sequence', 'number', 'booking_id', 'status', 'issued_at', 'issued_by',
    'seller_name', 'seller_tin', 'seller_address', 'seller_phone', 'seller_email',
    'customer_name', 'customer_tin', 'customer_phone', 'customer_email',
    'booking_reference', 'room_label', 'stay_check_in', 'stay_check_out', 'nights', 'guests',
    'currency', 'gross_amount', 'discount_amount', 'total_amount',
    'tax_mode', 'subtotal_amount', 'service_charge_rate', 'service_charge_amount',
    'vat_rate', 'vat_amount', 'vat_includes_service_charge',
    'amount_paid', 'balance_due',
    'voided_at', 'voided_by', 'void_reason',
])]
class Invoice extends Model
{
    /** The only columns that may change after the invoice is issued. */
    private const VOID_COLUMNS = ['status', 'voided_at', 'voided_by', 'void_reason', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            if ($invoice->getRawOriginal('status') === InvoiceStatus::Voided->value) {
                throw new LogicException('A voided invoice cannot be changed.');
            }

            if (array_diff(array_keys($invoice->getDirty()), self::VOID_COLUMNS) !== []) {
                throw new LogicException('An issued invoice cannot be changed. Void it and issue a new one.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Invoices are never deleted. Void the invoice instead.');
        });
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'stay_check_in' => 'date',
            'stay_check_out' => 'date',
            'nights' => 'integer',
            'guests' => 'integer',
            'gross_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'service_charge_rate' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'vat_includes_service_charge' => 'boolean',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }

    public function isVoided(): bool
    {
        return $this->status === InvoiceStatus::Voided;
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('line_no');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
