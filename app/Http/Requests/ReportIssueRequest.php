<?php

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Models\MaintenanceIssue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', MaintenanceIssue::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:500'],
            'priority' => ['required', Rule::enum(IssuePriority::class)],
            'blocks_room' => ['nullable', 'boolean'],
        ];
    }
}
