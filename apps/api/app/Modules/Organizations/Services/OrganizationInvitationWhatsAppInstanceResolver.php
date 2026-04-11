<?php

namespace App\Modules\Organizations\Services;

use App\Modules\Organizations\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppInstance;

class OrganizationInvitationWhatsAppInstanceResolver
{
    public function resolve(Organization $organization): ?WhatsAppInstance
    {
        $baseQuery = WhatsAppInstance::query()
            ->where('organization_id', $organization->id)
            ->active()
            ->connected();

        $organizationDefault = (clone $baseQuery)
            ->default()
            ->first();

        if ($organizationDefault) {
            return $organizationDefault;
        }

        $connectedInstances = (clone $baseQuery)
            ->limit(2)
            ->get();

        return $connectedInstances->count() === 1
            ? $connectedInstances->first()
            : null;
    }
}
