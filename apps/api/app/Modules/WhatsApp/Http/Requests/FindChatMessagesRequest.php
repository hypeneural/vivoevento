<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FindChatMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:whatsapp_instances,id'],
            'remote_jid' => ['required', 'string', 'max:190'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'before_message_id' => ['nullable', 'string', 'max:190'],
            'from_me' => ['nullable', 'boolean'],
        ];
    }
}
