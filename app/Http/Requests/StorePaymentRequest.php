<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Money received from a guest. Errors go to the "payment" bag so they show under this form only.
 */
class StorePaymentRequest extends FormRequest
{
    protected $errorBag = 'payment';

    public function authorize(): bool
    {
        return Gate::allows('create', Payment::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $base = (string) config('payments.currency', 'ETB');

        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => [
                Rule::requiredIf(fn (): bool => $this->input('method') !== PaymentMethod::Cash->value),
                'nullable', 'string', 'max:100',
            ],
            'currency' => ['nullable', Rule::in(app(PaymentService::class)->currencies())],
            'exchange_rate' => [
                Rule::requiredIf(fn (): bool => filled($this->input('currency')) && strtoupper((string) $this->input('currency')) !== $base),
                'nullable', 'numeric', 'gt:0', 'max:1000000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference.required' => 'Enter the reference number (bank slip, Telebirr or card receipt).',
            'exchange_rate.required' => 'Enter the exchange rate for this currency.',
        ];
    }
}
