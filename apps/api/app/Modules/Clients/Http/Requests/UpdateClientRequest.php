<?php

namespace App\Modules\Clients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.update');
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['prohibited'],
            'type' => ['sometimes', 'string', 'in:pessoa_fisica,empresa'],
            'name' => ['sometimes', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
