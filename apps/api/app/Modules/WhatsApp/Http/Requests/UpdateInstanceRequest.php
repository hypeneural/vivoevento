<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'provider_token' => ['sometimes', 'string', 'max:255'],
            'provider_client_token' => ['sometimes', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'settings_json' => ['nullable', 'array'],
        ];
    }
}
