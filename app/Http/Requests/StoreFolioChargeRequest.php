<?php

namespace App\Http\Requests;

use App\Enums\ChargeCategory;
use App\Models\FolioCharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFolioChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', FolioCharge::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(ChargeCategory::class)],
            'description' => ['required', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'unit_price' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ];
    }
}
