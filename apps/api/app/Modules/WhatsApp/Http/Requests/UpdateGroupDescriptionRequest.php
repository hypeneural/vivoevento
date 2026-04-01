<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:whatsapp_instances,id'],
            'group_description' => ['required', 'string', 'max:2048'],
        ];
    }
}
