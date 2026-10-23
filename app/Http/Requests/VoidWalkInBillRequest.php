<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidWalkInBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bill = $this->route('bill');

        return $bill !== null && (bool) $this->user()?->can('update', $bill);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }
}
