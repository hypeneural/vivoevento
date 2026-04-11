<?php

namespace App\Modules\Organizations\Services;

use App\Modules\Billing\Services\OrganizationEntitlementResolverService;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Validation\ValidationException;

class OrganizationBrandingEntitlementService
{
    public const ASSET_KINDS = [
        'logo',
        'logo_dark',
        'favicon',
        'watermark',
        'cover',
    ];

    public function __construct(
        private readonly OrganizationEntitlementResolverService $entitlements,
    ) {}

    public function canUseCustomDomain(Organization $organization): bool
    {
        return (bool) data_get($this->entitlements->resolve($organization), 'branding.custom_domain', false);
    }

    public function canUseAssetKind(Organization $organization, string $kind): bool
    {
        if ($kind === 'logo') {
            return true;
        }

        $branding = data_get($this->entitlements->resolve($organization), 'branding', []);

        if ($kind === 'watermark') {
            return (bool) ($branding['watermark'] ?? false);
        }

        return (bool) ($branding['expanded_assets'] ?? false);
    }

    public function assertCanUseCustomDomain(Organization $organization): void
    {
        if ($this->canUseCustomDomain($organization)) {
            return;
        }

        throw ValidationException::withMessages([
            'custom_domain' => 'Dominio proprio depende de um plano com white-label ou dominio customizado.',
        ]);
    }

    public function assertCanUseAssetKind(Organization $organization, string $kind): void
    {
        if ($this->canUseAssetKind($organization, $kind)) {
            return;
        }

        throw ValidationException::withMessages([
            'kind' => 'Este ativo de marca depende de um plano com branding white-label.',
        ]);
    }
}
