<?php

namespace App\Modules\Organizations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptPublicOrganizationInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
