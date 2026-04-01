<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendTextRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:4096'],
            'mentioned' => ['nullable', 'array'],
            'mentioned.*' => ['required_with:mentioned', 'string', 'min:10', 'max:40'],
            'delay_message' => ['nullable', 'integer', 'min:1', 'max:15'],
            'delay_typing' => ['nullable', 'integer', 'min:1', 'max:15'],
        ];
    }
}
