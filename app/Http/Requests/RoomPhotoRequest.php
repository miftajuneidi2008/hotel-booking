<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RoomPhotoRequest extends FormRequest
{
    public const MAX_PHOTOS_PER_ROOM = 10;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Room $room */
                $room = $this->route('room');

                if ($room->photos()->count() + count($this->file('photos')) > self::MAX_PHOTOS_PER_ROOM) {
                    $validator->errors()->add('photos', 'A room can have at most '.self::MAX_PHOTOS_PER_ROOM.' photos.');
                }
            },
        ];
    }
}
