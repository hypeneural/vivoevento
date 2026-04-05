<?php

namespace App\Modules\Play\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayGameRankingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->position,
            'player_identifier' => $this->player_identifier,
            'player_name' => $this->player_name,
            'best_score' => $this->best_score,
            'best_time_ms' => $this->best_time_ms,
            'best_moves' => $this->best_moves,
            'total_sessions' => $this->total_sessions,
            'total_wins' => $this->total_wins,
            'last_played_at' => $this->last_played_at?->toIso8601String(),
            'metrics' => $this->metrics_json ?? [],
        ];
    }
}
