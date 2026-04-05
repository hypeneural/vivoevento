<?php

namespace App\Modules\Play\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayAdminSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'event_game_id' => $this->event_game_id,
            'game' => $this->whenLoaded('eventGame', fn () => [
                'id' => $this->eventGame?->id,
                'uuid' => $this->eventGame?->uuid,
                'title' => $this->eventGame?->title,
                'slug' => $this->eventGame?->slug,
                'game_type_key' => $this->eventGame?->gameType?->key?->value ?? $this->eventGame?->gameType?->key,
                'game_type_name' => $this->eventGame?->gameType?->name,
            ]),
            'player_identifier' => $this->player_identifier,
            'player_name' => $this->player_name,
            'status' => $this->status?->value ?? $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'move_count' => $this->whenCounted('moves'),
            'result' => $this->result_json ?? [],
            'score' => $this->result_json['score'] ?? null,
            'time_ms' => $this->result_json['time_ms'] ?? null,
            'moves_reported' => $this->result_json['moves'] ?? null,
            'mistakes' => $this->result_json['mistakes'] ?? null,
            'accuracy' => $this->result_json['accuracy'] ?? null,
            'completed' => (bool) ($this->result_json['completed'] ?? false),
        ];
    }
}
