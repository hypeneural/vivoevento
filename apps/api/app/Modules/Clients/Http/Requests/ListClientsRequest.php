<?php

namespace App\Modules\Clients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.view');
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'search' => ['nullable', 'string', 'max:180'],
            'type' => ['nullable', Rule::in(['pessoa_fisica', 'empresa'])],
            'plan_code' => ['nullable', 'string', 'exists:plans,code'],
            'has_events' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['created_at', 'name', 'events_count'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
