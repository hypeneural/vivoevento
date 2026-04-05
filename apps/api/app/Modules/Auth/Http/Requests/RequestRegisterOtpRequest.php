<?php

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\Enums\RegisterJourneyType;
use App\Shared\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestRegisterOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'journey' => ['nullable', Rule::enum(RegisterJourneyType::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe seu nome.',
            'phone.required' => 'Informe seu WhatsApp.',
        ];
    }

    public function getName(): string
    {
        return trim($this->validated('name'));
    }

    public function getNormalizedPhone(): string
    {
        return PhoneNumber::normalizeBrazilianWhatsApp($this->validated('phone'));
    }

    public function journey(): RegisterJourneyType
    {
        $value = $this->validated('journey');

        return $value instanceof RegisterJourneyType
            ? $value
            : RegisterJourneyType::tryFrom((string) $value) ?? RegisterJourneyType::fallback();
    }
}
