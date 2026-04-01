<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Informe seu WhatsApp ou e-mail.',
            'code.required' => 'Informe o código de verificação.',
            'code.size' => 'O código deve ter 6 dígitos.',
            'password.required' => 'Informe a nova senha.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'As senhas não conferem.',
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
