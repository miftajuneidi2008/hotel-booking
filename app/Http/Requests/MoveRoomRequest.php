<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageBookings();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', Rule::exists(Room::class, 'id')->withoutTrashed()],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
