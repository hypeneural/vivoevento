<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Shared\Support\Helpers;

class CreateOrganizationAction
{
    public function execute(array $data): Organization
    {
        $displayName = $data['name'] ?? $data['trade_name'] ?? $data['legal_name'] ?? 'organizacao';

        $data['slug'] = Helpers::generateUniqueSlug(
            $displayName,
            Organization::class
        );

        return Organization::create($data);
    }
}
