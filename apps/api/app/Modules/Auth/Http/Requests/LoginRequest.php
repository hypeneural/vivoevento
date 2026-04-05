<?php

namespace App\Modules\Auth\Http\Requests;

use App\Shared\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Informe seu WhatsApp ou e-mail.',
            'password.required' => 'Informe sua senha.',
        ];
    }

    /**
     * Determine if the login field is a phone number or email.
     */
    public function isPhoneLogin(): bool
    {
        return PhoneNumber::looksLikeBrazilianPhone($this->validated('login'));
    }

    /**
     * Get the normalized login identifier.
     */
    public function getLoginIdentifier(): string
    {
        if ($this->isPhoneLogin()) {
            return PhoneNumber::normalizeBrazilianWhatsApp($this->validated('login'));
        }

        return strtolower(trim($this->validated('login')));
    }
}
