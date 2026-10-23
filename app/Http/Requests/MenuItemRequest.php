<?php

namespace App\Http\Requests;

use App\Enums\ChargeCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add or change a menu item. Only the owner and manager (MenuItemPolicy). */
class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $item instanceof MenuItem ? $user->can('update', $item) : $user->can('create', MenuItem::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('menu_items', 'name')->withoutTrashed()->ignore($this->route('item'))],
            'category' => ['required', Rule::enum(ChargeCategory::class)],
            'price' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
