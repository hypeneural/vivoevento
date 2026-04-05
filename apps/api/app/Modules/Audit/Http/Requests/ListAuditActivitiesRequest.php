<?php

namespace App\Modules\Audit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAuditActivitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('audit.view');
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject_type' => ['nullable', Rule::in(['event', 'organization', 'client', 'user', 'subscription', 'media'])],
            'activity_event' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:180'],
            'batch_uuid' => ['nullable', 'uuid'],
            'has_changes' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
