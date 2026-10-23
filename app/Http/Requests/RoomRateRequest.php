<?php

namespace App\Http\Requests;

use App\Enums\RateDays;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoomRateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'applies_on' => ['required', Rule::enum(RateDays::class)],
            'starts_on' => ['nullable', 'date', 'required_with:ends_on'],
            'ends_on' => ['nullable', 'date', 'required_with:starts_on', 'after_or_equal:starts_on'],
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

                $isEveryNight = $this->input('applies_on') === RateDays::AllDays->value;

                if ($isEveryNight && ! $this->filled('starts_on')) {
                    $validator->errors()->add('applies_on', 'An every-night rate needs a date range. Change the base rate on the room type instead.');
                }
            },
        ];
    }
}
