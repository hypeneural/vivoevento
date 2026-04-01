<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:whatsapp_instances,id'],
            'phone' => ['required', 'string', 'min:10', 'max:40'],
            'image' => ['required', 'string'],  // URL or base64
            'caption' => ['nullable', 'string', 'max:1024'],
            'delay_message' => ['nullable', 'integer', 'min:1', 'max:15'],
            'view_once' => ['nullable', 'boolean'],
        ];
    }
}
