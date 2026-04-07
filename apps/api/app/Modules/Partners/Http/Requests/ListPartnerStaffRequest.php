<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPartnerStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partner = $this->route('partner');

        return $partner instanceof Organization
            && (bool) $this->user()?->can('viewPartner', $partner);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:180'],
            'role_key' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['active', 'pending', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
