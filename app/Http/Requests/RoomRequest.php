<?php

namespace App\Http\Requests;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', Rule::exists(RoomType::class, 'id')->withoutTrashed()],
            'number' => ['required', 'string', 'max:20', Rule::unique(Room::class)->withoutTrashed()->ignore($this->route('room'))],
            'floor' => ['required', 'integer', 'min:-5', 'max:100'],
            'status' => ['required', Rule::enum(RoomStatus::class)],
        ];
    }
}
