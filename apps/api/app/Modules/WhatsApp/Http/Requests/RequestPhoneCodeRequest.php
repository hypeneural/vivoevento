<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestPhoneCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('channels.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+?\d{10,15}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D+/', '', (string) $this->input('phone')) ?? '';

        $this->merge([
            'phone' => $digits,
        ]);
    }
}
