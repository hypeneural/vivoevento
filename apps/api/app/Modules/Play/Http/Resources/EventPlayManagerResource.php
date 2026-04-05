<?php

namespace App\Modules\Play\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class EventPlayManagerResource extends JsonResource
{
    public function __construct($resource, private readonly Collection $catalog)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'event' => [
                'id' => $this->id,
                'title' => $this->title,
                'slug' => $this->slug,
                'status' => $this->status?->value,
            ],
            'settings' => [
                'is_enabled' => (bool) ($this->playSettings?->is_enabled ?? false),
                'memory_enabled' => (bool) ($this->playSettings?->memory_enabled ?? true),
                'puzzle_enabled' => (bool) ($this->playSettings?->puzzle_enabled ?? true),
                'memory_card_count' => (int) ($this->playSettings?->memory_card_count ?? 12),
                'puzzle_piece_count' => (int) ($this->playSettings?->puzzle_piece_count ?? 9),
                'auto_refresh_assets' => (bool) ($this->playSettings?->auto_refresh_assets ?? true),
                'ranking_enabled' => (bool) ($this->playSettings?->ranking_enabled ?? false),
            ],
            'catalog' => PlayGameTypeResource::collection($this->catalog),
            'games' => $this->whenLoaded('playGames', fn () => PlayEventGameResource::collection($this->playGames)),
        ];
    }
}
