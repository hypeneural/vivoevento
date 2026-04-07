<?php

namespace App\Modules\Partners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPartnersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAnyPartners');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:180'],
            'segment' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'plan_code' => ['nullable', 'string', 'max:80'],
            'subscription_status' => ['nullable', 'string', 'max:30'],
            'has_active_events' => ['nullable', 'boolean'],
            'has_clients' => ['nullable', 'boolean'],
            'has_active_bonus_grants' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['name', 'created_at', 'revenue_cents', 'active_events_count', 'clients_count', 'team_size'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
