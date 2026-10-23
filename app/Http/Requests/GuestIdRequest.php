<?php

namespace App\Http\Requests;

use App\Enums\IdType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ID or passport details taken at check-in (FR-11).
 */
class GuestIdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageBookings();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_type' => ['required', Rule::enum(IdType::class)],
            'id_number' => ['required', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['id_type' => 'ID type', 'id_number' => 'ID or passport number'];
    }
}
