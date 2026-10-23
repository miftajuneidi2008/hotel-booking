<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferFolioChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $charge = $this->route('charge');

        return $charge !== null && (bool) $this->user()?->can('transfer', $charge);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to_booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
