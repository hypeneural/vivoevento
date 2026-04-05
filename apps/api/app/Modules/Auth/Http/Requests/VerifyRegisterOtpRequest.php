<?php

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\Enums\RegisterJourneyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyRegisterOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token' => ['required', 'string', 'min:20', 'max:120'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:60'],
            'journey' => ['nullable', Rule::enum(RegisterJourneyType::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'session_token.required' => 'Sessao de cadastro invalida. Solicite um novo codigo.',
            'code.required' => 'Informe o codigo de verificacao.',
            'code.digits' => 'O codigo deve ter 6 digitos.',
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

    public function journey(): ?RegisterJourneyType
    {
        $value = $this->validated('journey');

        if ($value instanceof RegisterJourneyType) {
            return $value;
        }

        return $value ? RegisterJourneyType::tryFrom((string) $value) : null;
    }
}
