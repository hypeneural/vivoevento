<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && (bool) $this->user()?->can('update', $organization);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
        ];
    }
}
