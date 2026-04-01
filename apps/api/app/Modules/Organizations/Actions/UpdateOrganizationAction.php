<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\Organization;

class UpdateOrganizationAction
{
    public function execute(Organization $organization, array $data): Organization
    {
        $organization->update($data);

        return $organization->fresh();
    }
}
