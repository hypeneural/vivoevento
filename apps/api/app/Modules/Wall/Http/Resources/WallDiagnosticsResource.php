<?php

namespace App\Modules\Wall\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WallDiagnosticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->resource['summary'] ?? [],
            'players' => $this->resource['players'] ?? [],
            'updated_at' => $this->resource['updated_at'] ?? null,
        ];
    }
}
