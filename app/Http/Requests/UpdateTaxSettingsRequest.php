<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'vat_enabled' => ['required', 'boolean'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'service_charge_enabled' => ['required', 'boolean'],
            'service_charge_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'vat_on_service_charge' => ['required', 'boolean'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'business_tin' => ['nullable', 'string', 'max:30'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:40'],
            'business_email' => ['nullable', 'email', 'max:150'],
        ];
    }
}
