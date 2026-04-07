<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Support\CurrentOrganizationAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrentOrganizationBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentOrganizationAccess::canManageBranding($this->user());
    }

    public function rules(): array
    {
        $organization = $this->user()?->currentOrganization();

        return [
            'logo_path' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'subdomain' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('organizations', 'subdomain')->ignore($organization?->id),
            ],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('organizations', 'custom_domain')->ignore($organization?->id),
            ],
        ];
    }
}
