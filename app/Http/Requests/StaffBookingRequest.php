<?php

namespace App\Http\Requests;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Models\Room;
use App\Support\StayRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A receptionist booking a specific room for a guest at the desk (walk-in, phone or agent).
 */
class StaffBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageBookings();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return StayRules::rules() + [
            'room_id' => ['required', 'integer', Rule::exists(Room::class, 'id')->withoutTrashed()],
            'source' => ['required', Rule::enum(BookingSource::class)->only(BookingSource::desk())],
            'guest_name' => ['required', 'string', 'max:150'],
            'guest_phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'deposit_method' => [
                Rule::requiredIf(fn (): bool => (float) $this->input('deposit_amount') > 0),
                'nullable',
                Rule::enum(PaymentMethod::class),
            ],
            'deposit_reference' => ['nullable', 'string', 'max:100'],
            'check_in_now' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'guest_phone.regex' => 'Enter a valid phone number, for example +251 911 000 000.',
            'deposit_method.required' => 'Choose how the deposit was paid.',
        ];
    }
}
