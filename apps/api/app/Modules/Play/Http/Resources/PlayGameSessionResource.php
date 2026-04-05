<?php

namespace App\Modules\Play\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayGameSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'event_game_id' => $this->event_game_id,
            'player_identifier' => $this->player_identifier,
            'player_name' => $this->player_name,
            'status' => $this->status?->value ?? $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'result' => $this->result_json ?? [],
            'score' => $this->result_json['score'] ?? null,
            'time_ms' => $this->result_json['time_ms'] ?? null,
        ];
    }
}
