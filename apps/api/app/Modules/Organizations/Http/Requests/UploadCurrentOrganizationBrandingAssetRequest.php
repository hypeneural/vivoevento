<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Services\OrganizationBrandingEntitlementService;
use App\Modules\Organizations\Support\CurrentOrganizationAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadCurrentOrganizationBrandingAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentOrganizationAccess::canManageBranding($this->user());
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(OrganizationBrandingEntitlementService::ASSET_KINDS)],
            'asset' => ['required', 'image', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $organization = $this->user()?->currentOrganization();
            $kind = (string) $this->input('kind');

            if (! $organization) {
                return;
            }

            try {
                app(OrganizationBrandingEntitlementService::class)->assertCanUseAssetKind($organization, $kind);
            } catch (\Illuminate\Validation\ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'kind.required' => 'Informe qual ativo de marca sera enviado.',
            'asset.required' => 'Selecione uma imagem para o ativo.',
            'asset.image' => 'O arquivo precisa ser uma imagem.',
            'asset.max' => 'O ativo nao pode ter mais de 10MB.',
            'asset.mimes' => 'Formato aceito: JPG, PNG ou WebP.',
        ];
    }
}
