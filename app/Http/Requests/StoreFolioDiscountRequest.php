<?php

namespace App\Http\Requests;

use App\Enums\DiscountType;
use App\Models\FolioDiscount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFolioDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', FolioDiscount::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DiscountType::class)],
            'value' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
