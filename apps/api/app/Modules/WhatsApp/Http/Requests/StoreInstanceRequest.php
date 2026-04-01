<?php

namespace App\Modules\WhatsApp\Http\Requests;

use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization via Policy
    }

    public function rules(): array
    {
        return [
            'provider_key' => ['required', 'string', Rule::enum(WhatsAppProviderKey::class)],
            'name' => ['required', 'string', 'max:120'],
            'external_instance_id' => ['required', 'string', 'max:180'],
            'provider_token' => ['required', 'string', 'max:255'],
            'provider_client_token' => ['required', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
