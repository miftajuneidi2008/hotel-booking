<?php

namespace App\Http\Requests;

use App\Models\WalkInBill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An order from the menu, for a room bill or a walk-in bill (FR-28). */
class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', WalkInBill::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'target' => ['required', Rule::in(['room', 'walk_in'])],
            'booking_id' => ['required_if:target,room', 'nullable', 'integer', 'exists:bookings,id'],
            'walk_in_bill_id' => ['nullable', 'integer', 'exists:walk_in_bills,id'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item to the order.',
            'items.min' => 'Add at least one item to the order.',
            'booking_id.required_if' => 'Choose the guest whose room bill gets the charges.',
        ];
    }
}
