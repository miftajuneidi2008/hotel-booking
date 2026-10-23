<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeStayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageBookings();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // A stay can be extended up to a year from check-in, which stops typing mistakes.
            'checkout' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.today()->addYear()->toDateString()],
        ];
    }
}
