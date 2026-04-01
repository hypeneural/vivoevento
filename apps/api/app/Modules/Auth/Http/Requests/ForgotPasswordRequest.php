<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Informe seu WhatsApp ou e-mail.',
        ];
    }

    public function isPhoneLogin(): bool
    {
        $digits = preg_replace('/\D/', '', $this->validated('login'));
        return strlen($digits) >= 10 && strlen($digits) <= 13;
    }

    public function getLoginIdentifier(): string
    {
        if ($this->isPhoneLogin()) {
            $digits = preg_replace('/\D/', '', $this->validated('login'));
            if (strlen($digits) <= 11) {
                $digits = '55' . $digits;
            }
            return $digits;
        }
        return strtolower(trim($this->validated('login')));
    }
}
