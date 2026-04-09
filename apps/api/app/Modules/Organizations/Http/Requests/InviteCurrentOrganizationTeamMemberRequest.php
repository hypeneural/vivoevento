<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Support\CurrentOrganizationAccess;
use App\Shared\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteCurrentOrganizationTeamMemberRequest extends FormRequest
{
    private const ALLOWED_ROLE_KEYS = [
        'partner-owner',
        'partner-manager',
        'event-operator',
        'financeiro',
        'viewer',
    ];

    public function authorize(): bool
    {
        return CurrentOrganizationAccess::canManageTeam($this->user());
    }

    protected function prepareForValidation(): void
    {
        $user = (array) $this->input('user', []);
        $phone = PhoneNumber::normalizeBrazilianWhatsAppOrNull($user['phone'] ?? null);
        $email = isset($user['email']) ? trim((string) $user['email']) : null;

        $this->merge([
            'user' => array_merge($user, [
                'phone' => $phone,
                'email' => $email !== '' ? $email : null,
            ]),
        ]);
    }

    public function rules(): array
    {
        return [
            'user' => ['required', 'array'],
            'user.name' => ['required', 'string', 'max:160'],
            'user.email' => ['nullable', 'email', 'max:160'],
            'user.phone' => ['required', 'string', 'max:40'],
            'role_key' => ['required', 'string', Rule::in(self::ALLOWED_ROLE_KEYS)],
            'is_owner' => ['sometimes', 'boolean'],
        ];
    }
}
