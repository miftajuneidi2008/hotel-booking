<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class VoidPaymentRequest extends FormRequest
{
    protected $errorBag = 'void';

    public function authorize(): bool
    {
        return Gate::allows('void', $this->route('payment'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:255']];
    }
}
