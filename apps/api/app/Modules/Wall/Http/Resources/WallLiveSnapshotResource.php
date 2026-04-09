<?php

namespace App\Modules\Wall\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WallLiveSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'wallStatus' => $this->resource['wallStatus'] ?? 'draft',
            'wallStatusLabel' => $this->resource['wallStatusLabel'] ?? 'Rascunho',
            'layout' => $this->resource['layout'] ?? 'auto',
            'transitionEffect' => $this->resource['transitionEffect'] ?? 'fade',
            'currentPlayer' => $this->resource['currentPlayer'] ?? null,
            'currentItem' => $this->resource['currentItem'] ?? null,
            'nextItem' => $this->resource['nextItem'] ?? null,
            'advancedAt' => $this->resource['advancedAt'] ?? null,
            'updatedAt' => $this->resource['updatedAt'] ?? null,
        ];
    }
}
