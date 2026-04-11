<?php

namespace App\Modules\Play\Http\Resources;

use App\Modules\Play\DTOs\GameLaunchReadinessDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayEventGameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $readiness = $this->resource->getAttribute('readiness');

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'event_id' => $this->event_id,
            'game_type_key' => $this->gameType?->key?->value ?? $this->gameType?->key,
            'game_type_name' => $this->gameType?->name,
            'title' => $this->title,
            'slug' => $this->slug,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'ranking_enabled' => (bool) $this->ranking_enabled,
            'settings' => $this->settings_json ?? [],
            'readiness' => $this->when($readiness !== null, function () use ($readiness) {
                if ($readiness instanceof GameLaunchReadinessDTO) {
                    return $readiness->toArray();
                }

                return $readiness;
            }),
            'assets' => $this->whenLoaded('assets', fn () => PlayGameAssetResource::collection($this->assets)),
            'assets_count' => $this->whenCounted('assets'),
            'sessions_count' => $this->whenCounted('sessions'),
            'rankings_count' => $this->whenCounted('rankings'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
