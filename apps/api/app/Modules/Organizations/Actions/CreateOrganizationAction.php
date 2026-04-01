<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Shared\Support\Helpers;

class CreateOrganizationAction
{
    public function execute(array $data): Organization
    {
        $data['slug'] = Helpers::generateUniqueSlug(
            $data['name'],
            Organization::class
        );

        return Organization::create($data);
    }
}
