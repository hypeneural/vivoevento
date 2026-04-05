<?php

namespace App\Modules\Hub\Queries;

use App\Modules\Hub\Models\HubPreset;
use Illuminate\Database\Eloquent\Collection;

class ListHubPresetsQuery
{
    public function execute(int $organizationId): Collection
    {
        return HubPreset::query()
            ->with(['creator:id,name', 'sourceEvent:id,title,slug'])
            ->forOrganization($organizationId)
            ->latest()
            ->get();
    }
}
