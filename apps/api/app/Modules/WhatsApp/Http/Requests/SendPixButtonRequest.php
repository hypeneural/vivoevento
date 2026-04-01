<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendPixButtonRequest extends FormRequest
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
            'pix_key' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:CPF,CNPJ,PHONE,EMAIL,EVP'],
            'merchant_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
