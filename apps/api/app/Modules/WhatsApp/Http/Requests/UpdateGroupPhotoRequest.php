<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:whatsapp_instances,id'],
            'group_photo' => ['required', 'string'], // URL or base64
        ];
    }
}
