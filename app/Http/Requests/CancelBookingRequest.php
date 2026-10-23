<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Business rule 3: every cancellation needs a reason.
        return ['reason' => ['required', 'string', 'min:3', 'max:255']];
    }
}
