<?php

namespace App\Modules\Auth\Http\Requests;

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
        $login = $this->validated('login');
        // If it contains only digits (after stripping formatting), it's a phone
        $digits = preg_replace('/\D/', '', $login);
        return strlen($digits) >= 10 && strlen($digits) <= 13;
    }

    /**
     * Get the normalized login identifier.
     */
    public function getLoginIdentifier(): string
    {
        if ($this->isPhoneLogin()) {
            // Normalize phone: strip everything except digits
            $digits = preg_replace('/\D/', '', $this->validated('login'));
            // Ensure starts with country code 55
            if (strlen($digits) <= 11) {
                $digits = '55' . $digits;
            }
            return $digits;
        }

        return strtolower(trim($this->validated('login')));
    }
}
