<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
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

    /**
     * The receptionist orders for guests in a room only. The cashier (and the owner and manager) can also order for walk-in customers.
     *
     * @return list<string>
     */
    private function targets(): array
    {
        return $this->user()?->canOrderForWalkIn() ? ['room', 'walk_in'] : ['room'];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'target' => ['required', Rule::in($this->targets())],
            'booking_id' => ['required_if:target,room', 'nullable', 'integer', 'exists:bookings,id'],
            // The cashier needs the table number to know where a front-desk order goes, so the receptionist must give it.
            'table_number' => [
                Rule::requiredIf(fn (): bool => $this->user()?->role === UserRole::Receptionist && $this->input('target') === 'room'),
                'nullable', 'string', 'max:20',
            ],
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
            'target.in' => 'You can only add orders to the room bill of a guest in the house. Walk-in customers are served by the restaurant cashier.',
            'table_number.required' => 'Enter the table number, so the restaurant cashier knows where the order goes.',
            'table_number.max' => 'The table number can have at most 20 characters.',
        ];
    }
}
