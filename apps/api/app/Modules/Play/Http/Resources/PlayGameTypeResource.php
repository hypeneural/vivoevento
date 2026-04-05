<?php

namespace App\Modules\Play\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayGameTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key?->value ?? $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'enabled' => (bool) $this->enabled,
            'supports_ranking' => (bool) $this->supports_ranking,
            'supports_photo_assets' => (bool) $this->supports_photo_assets,
            'config_schema' => $this->config_schema_json ?? [],
        ];
    }
}
