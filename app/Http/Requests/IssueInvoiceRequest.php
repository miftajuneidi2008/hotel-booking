<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Issuing an invoice. The customer name and TIN are optional: the guest's name is used when the name is empty. */
class IssueInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Invoice::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_tin' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/ ]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['customer_tin.regex' => 'The customer TIN can only have letters, numbers, spaces, "-" and "/".'];
    }
}
