<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** One line of an invoice. Written once when the invoice is issued, never changed or deleted. */
#[Fillable(['invoice_id', 'line_no', 'type', 'category', 'description', 'quantity', 'unit_price', 'amount'])]
class InvoiceLine extends Model
{
    public const ROOM = 'room';

    public const CHARGE = 'charge';

    public const DISCOUNT = 'discount';

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Invoice lines cannot be changed.');
        });

        static::deleting(function (): never {
            throw new LogicException('Invoice lines cannot be deleted.');
        });
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
