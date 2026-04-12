<?php

namespace App\Modules\Gallery\Queries;

use App\Modules\Gallery\Models\GalleryPreset;
use Illuminate\Database\Eloquent\Collection;

class ListGalleryPresetsQuery
{
    /**
     * @return Collection<int, GalleryPreset>
     */
    public function execute(int $organizationId): Collection
    {
        return GalleryPreset::query()
            ->where('organization_id', $organizationId)
            ->with(['creator:id,name', 'sourceEvent:id,title,slug'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }
}
