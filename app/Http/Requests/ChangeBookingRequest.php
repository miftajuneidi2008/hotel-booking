<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Models\Room;
use App\Support\StayRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A receptionist changing a confirmed booking.
 */
class ChangeBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageBookings();
    }

    /**
     * Same date rules as a new booking, except that a check-in date that is left
     * unchanged is accepted even if it is already today or earlier.
     *
     * @return array<string, mixed>
     */
    public static function stayRulesFor(Booking $booking): array
    {
        $rules = StayRules::rules();

        $rules['checkin'] = [
            'required',
            'date',
            function (string $attribute, mixed $value, Closure $fail) use ($booking): void {
                $time = strtotime((string) $value);

                if ($time !== false && $time !== strtotime($booking->check_in->toDateString()) && $time < strtotime('today')) {
                    $fail('The check-in date cannot be in the past.');
                }
            },
        ];

        return $rules;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::stayRulesFor($this->route('booking')) + [
            'room_id' => ['required', 'integer', Rule::exists(Room::class, 'id')->withoutTrashed()],
            'guest_name' => ['required', 'string', 'max:150'],
            'guest_phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['guest_phone.regex' => 'Enter a valid phone number, for example +251 911 000 000.'];
    }
}
