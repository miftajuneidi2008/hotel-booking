<?php

namespace App\Models;

use App\Enums\ChargeCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One line on a walk-in bill. Removing a line only hides it (business rule 7). */
#[Fillable(['walk_in_bill_id', 'menu_item_id', 'category', 'description', 'quantity', 'unit_price', 'amount', 'created_by'])]
class WalkInBillLine extends Model
{
    use SoftDeletes;

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'category' => ChargeCategory::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<WalkInBill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(WalkInBill::class, 'walk_in_bill_id');
    }
}
