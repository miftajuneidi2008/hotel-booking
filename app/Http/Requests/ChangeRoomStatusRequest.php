<?php

namespace App\Http\Requests;

use App\Models\RoomStatusChange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeRoomStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', RoomStatusChange::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Occupied follows check-in and out of service follows a maintenance issue, so neither is chosen here.
            'status' => ['required', Rule::in(['clean', 'dirty'])],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
