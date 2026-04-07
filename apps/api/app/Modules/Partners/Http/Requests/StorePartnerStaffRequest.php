<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class StorePartnerStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partner = $this->route('partner');

        return $partner instanceof Organization
            && (bool) $this->user()?->can('managePartnerStaff', $partner);
    }

    public function rules(): array
    {
        return [
            'user' => ['required', 'array'],
            'user.name' => ['required', 'string', 'max:160'],
            'user.email' => ['required', 'email', 'max:160'],
            'user.phone' => ['nullable', 'string', 'max:40'],
            'role_key' => ['required', 'string', 'max:60'],
            'is_owner' => ['nullable', 'boolean'],
            'send_invite' => ['nullable', 'boolean'],
        ];
    }
}
