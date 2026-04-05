<?php

namespace App\Modules\Auth\Http\Requests;

use App\Shared\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $login = trim((string) $this->input('login'));

            if ($login === '') {
                return;
            }

            if (
                ! PhoneNumber::looksLikeBrazilianPhone($login)
                && filter_var($login, FILTER_VALIDATE_EMAIL) === false
            ) {
                $validator->errors()->add('login', 'Informe um WhatsApp com DDD ou e-mail valido.');
            }
        });
    }

    public function isPhoneLogin(): bool
    {
        return PhoneNumber::looksLikeBrazilianPhone($this->validated('login'));
    }

    public function getLoginIdentifier(): string
    {
        if ($this->isPhoneLogin()) {
            return PhoneNumber::normalizeBrazilianWhatsApp($this->validated('login'));
        }
        return strtolower(trim($this->validated('login')));
    }
}
