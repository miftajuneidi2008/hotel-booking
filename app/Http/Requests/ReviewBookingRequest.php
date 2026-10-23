<?php

namespace App\Http\Requests;

use App\Models\RoomType;
use App\Support\StayRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The "review your booking" page: the dates and the room type the guest picked.
 */
class ReviewBookingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return StayRules::rules() + [
            'type' => ['required', 'string', Rule::exists(RoomType::class, 'slug')->withoutTrashed()],
        ];
    }
}
