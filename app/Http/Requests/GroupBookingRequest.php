<?php

namespace App\Http\Requests;

use App\Enums\BookingSource;
use App\Models\Room;
use App\Support\StayRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * A receptionist booking several rooms together for a group or company.
 */
class GroupBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageBookings();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return Arr::only(StayRules::rules(), ['checkin', 'checkout']) + [
            'room_ids' => ['required', 'array', 'min:1', 'max:30'],
            'room_ids.*' => ['integer', 'distinct', Rule::exists(Room::class, 'id')->withoutTrashed()],
            'guests' => ['nullable', 'array'],
            'guests.*' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('hotel.booking.max_guests', 10)],
            'source' => ['required', Rule::enum(BookingSource::class)->only(BookingSource::desk())],
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'room_ids.required' => 'Choose at least one room.',
            'contact_phone.regex' => 'Enter a valid phone number, for example +251 911 000 000.',
        ];
    }
}
