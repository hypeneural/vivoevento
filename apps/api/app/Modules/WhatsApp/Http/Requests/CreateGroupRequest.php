<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:whatsapp_instances,id'],
            'group_name' => ['required', 'string', 'max:120'],
            'phones' => ['required', 'array', 'min:1'],
            'phones.*' => ['required', 'string', 'min:10', 'max:40'],
            'auto_invite' => ['nullable', 'boolean'],
        ];
    }
}
