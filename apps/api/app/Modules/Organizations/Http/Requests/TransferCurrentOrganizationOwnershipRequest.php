<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Support\CurrentOrganizationAccess;
use Illuminate\Foundation\Http\FormRequest;

class TransferCurrentOrganizationOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentOrganizationAccess::canTransferOwnership($this->user());
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:organization_members,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'Selecione quem sera o novo titular.',
            'member_id.exists' => 'Selecione um membro ativo da organizacao atual.',
        ];
    }
}
