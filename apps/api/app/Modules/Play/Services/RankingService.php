<?php

namespace App\Modules\Play\Services;

use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Models\PlayGameRanking;
use App\Modules\Play\Models\PlayGameSession;
use Illuminate\Support\Collection;

class RankingService
{
    public function updateFromSession(PlayGameSession $session): PlayGameRanking
    {
        $result = $session->result_json ?? [];

        $ranking = PlayGameRanking::query()->firstOrNew([
            'event_game_id' => $session->event_game_id,
            'player_identifier' => $session->player_identifier,
        ]);

        $ranking->player_name = $session->player_name;
        $ranking->last_played_at = $session->finished_at ?? now();
        $ranking->total_sessions = ($ranking->exists ? $ranking->total_sessions : 0) + 1;
        $ranking->total_wins = ($ranking->exists ? $ranking->total_wins : 0) + (! empty($result['completed']) ? 1 : 0);

        $incomingScore = (int) ($result['score'] ?? 0);
        $incomingTime = isset($result['time_ms']) ? (int) $result['time_ms'] : null;
        $incomingMoves = isset($result['moves']) ? (int) $result['moves'] : null;

        if (! $ranking->exists || $this->isBetterResult($ranking, $incomingScore, $incomingTime, $incomingMoves)) {
            $ranking->best_score = $incomingScore;
            $ranking->best_time_ms = $incomingTime;
            $ranking->best_moves = $incomingMoves;
        }

        $ranking->metrics_json = array_merge(
            $ranking->metrics_json ?? [],
            ['last_result' => $result],
        );
        $ranking->save();

        return $ranking->fresh();
    }

    /**
     * @return Collection<int, PlayGameRanking>
     */
    public function leaderboard(PlayEventGame $game, int $limit = 20): Collection
    {
        return PlayGameRanking::query()
            ->where('event_game_id', $game->id)
            ->orderByDesc('best_score')
            ->orderByRaw('best_time_ms IS NULL')
            ->orderBy('best_time_ms')
            ->orderByRaw('best_moves IS NULL')
            ->orderBy('best_moves')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, PlayGameRanking>
     */
    public function leaderboardWithPositions(PlayEventGame $game, int $limit = 20): Collection
    {
        return $this->leaderboard($game, $limit)
            ->values()
            ->map(function (PlayGameRanking $item, int $index) {
                $item->position = $index + 1;

                return $item;
            });
    }

    private function isBetterResult(
        PlayGameRanking $ranking,
        int $incomingScore,
        ?int $incomingTime,
        ?int $incomingMoves,
    ): bool {
        if ($incomingScore > (int) $ranking->best_score) {
            return true;
        }

        if ($incomingScore < (int) $ranking->best_score) {
            return false;
        }

        if ($incomingTime !== null && ($ranking->best_time_ms === null || $incomingTime < $ranking->best_time_ms)) {
            return true;
        }

        return $incomingTime === $ranking->best_time_ms
            && $incomingMoves !== null
            && ($ranking->best_moves === null || $incomingMoves < $ranking->best_moves);
    }
}
