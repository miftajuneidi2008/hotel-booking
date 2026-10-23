<?php

namespace App\Support;

use Closure;

/**
 * Validation rules shared by every form that picks dates and a guest count.
 * Field names match the search bar: checkin, checkout, guests.
 */
final class StayRules
{
    /**
     * @return array<string, list<string|Closure>>
     */
    public static function rules(): array
    {
        $maxNights = (int) config('hotel.booking.max_nights', 30);
        $maxGuests = (int) config('hotel.booking.max_guests', 10);

        return [
            'checkin' => ['required', 'date', 'after_or_equal:today'],
            'checkout' => [
                'required',
                'date',
                'after:checkin',
                function (string $attribute, mixed $value, Closure $fail) use ($maxNights): void {
                    $checkin = request()->input('checkin');

                    if (! $checkin || strtotime((string) $checkin) === false || strtotime((string) $value) === false) {
                        return;
                    }

                    $nights = (int) round((strtotime((string) $value) - strtotime((string) $checkin)) / 86400);

                    if ($nights > $maxNights) {
                        $fail(__('You can book at most :nights nights at a time.', ['nights' => $maxNights]));
                    }
                },
            ],
            'guests' => ['required', 'integer', 'min:1', 'max:'.$maxGuests],
        ];
    }

    /**
     * Turn validated input into the YYYY-MM-DD strings the booking service expects.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: string, 2: int}
     */
    public static function normalise(array $validated): array
    {
        return [
            date('Y-m-d', strtotime((string) $validated['checkin'])),
            date('Y-m-d', strtotime((string) $validated['checkout'])),
            (int) $validated['guests'],
        ];
    }
}
