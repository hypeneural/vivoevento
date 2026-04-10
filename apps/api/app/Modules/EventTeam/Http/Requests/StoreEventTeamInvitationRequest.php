<?php

namespace App\Modules\EventTeam\Http\Requests;

use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use App\Shared\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventTeamInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $invitee = (array) $this->input('invitee', []);
        $phone = PhoneNumber::normalizeBrazilianWhatsAppOrNull($invitee['phone'] ?? null);
        $email = isset($invitee['email']) ? trim((string) $invitee['email']) : null;

        $this->merge([
            'invitee' => array_merge($invitee, [
                'phone' => $phone,
                'email' => $email !== '' ? $email : null,
            ]),
        ]);
    }

    public function rules(): array
    {
        $registry = app(EventAccessPresetRegistry::class);

        return [
            'invitee' => ['required', 'array'],
            'invitee.name' => ['required', 'string', 'max:160'],
            'invitee.email' => ['nullable', 'email', 'max:160'],
            'invitee.phone' => ['required', 'string', 'max:40'],
            'preset_key' => ['required', 'string', Rule::in($registry->eventPresetKeys())],
            'send_via_whatsapp' => ['nullable', 'boolean'],
        ];
    }
}
