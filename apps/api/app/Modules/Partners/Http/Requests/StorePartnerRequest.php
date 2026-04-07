<?php

namespace App\Modules\Partners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('createPartner');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:160', 'unique:organizations,email'],
            'billing_email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'segment' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
            'owner' => ['required', 'array'],
            'owner.name' => ['required', 'string', 'max:160'],
            'owner.email' => ['required', 'email', 'max:160'],
            'owner.phone' => ['nullable', 'string', 'max:40'],
            'owner.send_invite' => ['nullable', 'boolean'],
        ];
    }
}
