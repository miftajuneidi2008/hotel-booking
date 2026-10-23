<?php

namespace App\Http\Requests;

use App\Models\RoomType;
use App\Support\StayRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A guest booking a room type for themselves on the website.
 */
class StoreBookingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return StayRules::rules() + [
            'type' => ['required', 'string', Rule::exists(RoomType::class, 'slug')->withoutTrashed()],
            'guest_name' => ['required', 'string', 'max:150'],
            'guest_phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['guest_phone.regex' => __('Enter a valid phone number, for example +251 911 000 000.')];
    }
}
