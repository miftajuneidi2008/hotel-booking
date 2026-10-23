<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageBookings();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'payment_method' => [
                Rule::requiredIf(fn (): bool => (float) $this->input('payment_amount') > 0),
                'nullable',
                Rule::enum(PaymentMethod::class),
            ],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'override_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['payment_method.required' => 'Choose how the guest paid.'];
    }
}
