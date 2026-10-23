<?php

namespace App\Http\Requests;

use App\Models\MaintenanceIssue;
use Illuminate\Foundation\Http\FormRequest;

class ResolveIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $issue = $this->route('issue');

        return $issue instanceof MaintenanceIssue && (bool) $this->user()?->can('resolve', $issue);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:255']];
    }
}
