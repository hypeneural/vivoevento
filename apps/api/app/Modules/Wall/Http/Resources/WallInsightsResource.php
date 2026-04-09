<?php

namespace App\Modules\Wall\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WallInsightsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'topContributor' => $this->resource['topContributor'] ?? null,
            'totals' => $this->resource['totals'] ?? [
                'received' => 0,
                'approved' => 0,
                'queued' => 0,
                'displayed' => 0,
            ],
            'recentItems' => $this->resource['recentItems'] ?? [],
            'sourceMix' => $this->resource['sourceMix'] ?? [],
            'lastCaptureAt' => $this->resource['lastCaptureAt'] ?? null,
        ];
    }
}
