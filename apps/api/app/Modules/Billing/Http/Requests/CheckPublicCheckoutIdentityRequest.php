<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckPublicCheckoutIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = trim((string) $this->input('email', ''));

        $this->merge([
            'whatsapp' => trim((string) $this->input('whatsapp', '')),
            'email' => $email === '' ? null : $email,
        ]);
    }

    public function rules(): array
    {
        return [
            'whatsapp' => ['required', 'string', 'min:8'],
            'email' => ['nullable', 'email'],
        ];
    }
}
