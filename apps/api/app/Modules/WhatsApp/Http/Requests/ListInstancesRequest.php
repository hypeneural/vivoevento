<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInstancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('channels.view') ?? false)
            || ($this->user()?->can('channels.manage') ?? false);
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'search' => ['nullable', 'string', 'max:180'],
            'provider_key' => ['nullable', Rule::in(['zapi', 'evolution'])],
            'status' => ['nullable', Rule::in(['draft', 'configured', 'connected', 'disconnected', 'invalid_credentials', 'error', 'pending', 'connecting'])],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
