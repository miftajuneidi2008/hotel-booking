<?php

namespace App\Models;

use App\Enums\ChargeCategory;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A menu or service item with a price (FR-27). Hidden, never deleted for good. */
#[Fillable(['name', 'category', 'price', 'description', 'is_active'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'category' => ChargeCategory::class,
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Items that can be sold now. */
    public function scopeSellable(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
