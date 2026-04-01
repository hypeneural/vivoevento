<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:whatsapp_instances,id'],
            'admin_only_message' => ['required', 'boolean'],
            'admin_only_settings' => ['required', 'boolean'],
            'require_admin_approval' => ['required', 'boolean'],
            'admin_only_add_member' => ['required', 'boolean'],
        ];
    }
}
