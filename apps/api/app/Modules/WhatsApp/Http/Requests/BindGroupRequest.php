<?php

namespace App\Modules\WhatsApp\Http\Requests;

use App\Modules\WhatsApp\Enums\GroupBindingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BindGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:whatsapp_instances,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'group_name' => ['nullable', 'string', 'max:180'],
            'binding_type' => ['required', 'string', Rule::enum(GroupBindingType::class)],
            'metadata' => ['nullable', 'array'],
            'metadata.auto_reaction' => ['nullable', 'string', 'max:10'],
            'metadata.auto_reaction_enabled' => ['nullable', 'boolean'],
        ];
    }
}
