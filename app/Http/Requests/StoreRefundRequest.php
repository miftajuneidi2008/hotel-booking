<?php

namespace App\Http\Requests;

/**
 * Money paid back to a guest: the same fields as a payment, plus a required reason.
 */
class StoreRefundRequest extends StorePaymentRequest
{
    protected $errorBag = 'refund';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
