<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResendForgotPasswordOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token' => ['required', 'string', 'min:20', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'session_token.required' => 'Sessao de recuperacao invalida. Solicite um novo codigo.',
        ];
    }

    public function sessionToken(): string
    {
        return $this->validated('session_token');
    }
}
