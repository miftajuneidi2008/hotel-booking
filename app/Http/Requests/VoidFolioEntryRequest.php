<?php

namespace App\Http\Requests;

use App\Models\FolioCharge;
use App\Models\FolioDiscount;
use Illuminate\Foundation\Http\FormRequest;

/** Voiding a charge or removing a discount: both need a reason (business rule 3). */
class VoidFolioEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('charge') ?? $this->route('discount');

        return $entry instanceof FolioCharge || $entry instanceof FolioDiscount
            ? (bool) $this->user()?->can('void', $entry)
            : false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }
}
