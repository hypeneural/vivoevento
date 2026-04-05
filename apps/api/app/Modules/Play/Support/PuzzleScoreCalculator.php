<?php

namespace App\Modules\Play\Support;

use App\Modules\Play\DTOs\FinishGameSessionDTO;
use App\Modules\Play\Models\PlayGameSession;

class PuzzleScoreCalculator implements GameScoreCalculatorInterface
{
    public function calculate(PlayGameSession $session, FinishGameSessionDTO $dto): array
    {
        $session->loadMissing(['moves', 'eventGame']);

        $dropMoves = $session->moves->where('move_type', 'drop');
        $serverMoves = $dropMoves->count();
        $serverMistakes = $dropMoves
            ->filter(fn ($move) => ! ((bool) data_get($move->payload_json, 'snapped', false)))
            ->count();
        $timeMs = max($this->serverElapsedMs($session), $dto->timeMs);
        $normalizedMoves = max($serverMoves, $dto->moves);
        $normalizedMistakes = max($serverMistakes, (int) ($dto->mistakes ?? 0));
        $elapsedSeconds = (int) ceil($timeMs / 1000);
        $accuracy = $normalizedMoves > 0
            ? round(max(0, ($normalizedMoves - $normalizedMistakes) / $normalizedMoves), 4)
            : 1.0;

        return [
            'score' => $dto->completed
                ? max(0, 1200 - ($elapsedSeconds * 5) - ($normalizedMoves * 2) - ($normalizedMistakes * 8))
                : 0,
            'completed' => $dto->completed,
            'time_ms' => $timeMs,
            'moves' => $normalizedMoves,
            'mistakes' => $normalizedMistakes,
            'accuracy' => $accuracy,
            'metadata' => array_merge($dto->metadata, [
                'gridSize' => $session->eventGame->settings_json['gridSize'] ?? $dto->metadata['gridSize'] ?? null,
                'scoringVersion' => 'puzzle_v1',
            ]),
            'authoritative' => true,
            'client_result' => [
                'score' => $dto->score,
                'time_ms' => $dto->timeMs,
                'moves' => $dto->moves,
                'mistakes' => $dto->mistakes,
                'accuracy' => $dto->accuracy,
            ],
            'server_result' => [
                'time_ms' => $this->serverElapsedMs($session),
                'moves' => $serverMoves,
                'mistakes' => $serverMistakes,
            ],
        ];
    }

    private function serverElapsedMs(PlayGameSession $session): int
    {
        return max(0, (int) ($session->started_at?->diffInMilliseconds(now()) ?? 0));
    }
}
