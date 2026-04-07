<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPartnerClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partner = $this->route('partner');

        return $partner instanceof Organization
            && (bool) $this->user()?->can('viewPartner', $partner);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:180'],
            'type' => ['nullable', Rule::in(['pessoa_fisica', 'empresa'])],
            'plan_code' => ['nullable', 'string', 'max:80'],
            'has_events' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['name', 'created_at', 'events_count'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
