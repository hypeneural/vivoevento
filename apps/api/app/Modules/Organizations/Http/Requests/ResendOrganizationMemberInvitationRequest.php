<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Support\CurrentOrganizationAccess;
use Illuminate\Foundation\Http\FormRequest;

class ResendOrganizationMemberInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentOrganizationAccess::canManageTeam($this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'send_via_whatsapp' => $this->boolean('send_via_whatsapp', true),
        ]);
    }

    public function rules(): array
    {
        return [
            'send_via_whatsapp' => ['sometimes', 'boolean'],
        ];
    }
}
