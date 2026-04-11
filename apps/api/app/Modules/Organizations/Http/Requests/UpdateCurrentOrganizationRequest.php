<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Support\CurrentOrganizationAccess;
use App\Modules\Organizations\Services\OrganizationBrandingEntitlementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrentOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentOrganizationAccess::canManageSettings($this->user());
    }

    public function rules(): array
    {
        $organization = $this->user()?->currentOrganization();

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'trade_name' => ['sometimes', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'billing_email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'slug' => [
                'sometimes',
                'string',
                'max:120',
                Rule::unique('organizations', 'slug')->ignore($organization?->id),
            ],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('organizations', 'custom_domain')->ignore($organization?->id),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('custom_domain')) {
                return;
            }

            $organization = $this->user()?->currentOrganization();

            if (! $organization) {
                return;
            }

            if (! app(OrganizationBrandingEntitlementService::class)->canUseCustomDomain($organization)) {
                $validator->errors()->add(
                    'custom_domain',
                    'Dominio proprio depende de um plano com white-label ou dominio customizado.'
                );
            }
        });
    }
}
