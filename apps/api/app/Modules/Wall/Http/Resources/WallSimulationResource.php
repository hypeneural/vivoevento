<?php

namespace App\Modules\Wall\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WallSimulationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->resource['summary'] ?? [],
            'sequence_preview' => $this->resource['sequence_preview'] ?? [],
            'explanation' => $this->resource['explanation'] ?? [],
        ];
    }
}
