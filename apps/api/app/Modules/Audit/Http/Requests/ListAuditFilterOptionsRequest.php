<?php

namespace App\Modules\Audit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListAuditFilterOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('audit.view');
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ];
    }
}
