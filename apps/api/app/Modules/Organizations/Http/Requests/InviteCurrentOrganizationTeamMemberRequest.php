<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Support\CurrentOrganizationAccess;
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

    public function rules(): array
    {
        return [
            'user' => ['required', 'array'],
            'user.name' => ['required', 'string', 'max:160'],
            'user.email' => ['required', 'email', 'max:160'],
            'user.phone' => ['nullable', 'string', 'max:40'],
            'role_key' => ['required', 'string', Rule::in(self::ALLOWED_ROLE_KEYS)],
            'is_owner' => ['sometimes', 'boolean'],
        ];
    }
}
