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
            'session_token' => ['required', 'string', 'min:20', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'session_token.required' => 'Sessao de recuperacao invalida. Solicite um novo codigo.',
            'password.required' => 'Informe a nova senha.',
            'password.min' => 'A senha deve ter no minimo 8 caracteres.',
            'password.confirmed' => 'As senhas nao conferem.',
        ];
    }

    public function sessionToken(): string
    {
        return $this->validated('session_token');
    }

    public function deviceName(): string
    {
        return $this->validated('device_name', 'web-panel');
    }
}
