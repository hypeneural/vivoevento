<?php

namespace App\Modules\Play\Support;

use App\Modules\Play\DTOs\FinishGameSessionDTO;
use App\Modules\Play\Models\PlayGameSession;

class MemoryScoreCalculator implements GameScoreCalculatorInterface
{
    public function calculate(PlayGameSession $session, FinishGameSessionDTO $dto): array
    {
        $session->loadMissing(['moves', 'eventGame']);

        $moves = $session->moves->whereIn('move_type', ['match', 'mismatch']);
        $serverMoves = $moves->count();
        $serverMistakes = $session->moves->where('move_type', 'mismatch')->count();
        $timeMs = max($this->serverElapsedMs($session), $dto->timeMs);
        $normalizedMoves = max($serverMoves, $dto->moves);
        $normalizedMistakes = max($serverMistakes, (int) ($dto->mistakes ?? 0));
        $elapsedSeconds = (int) ceil($timeMs / 1000);
        $accuracy = $normalizedMoves > 0
            ? round(max(0, ($normalizedMoves - $normalizedMistakes) / $normalizedMoves), 4)
            : 1.0;

        return [
            'score' => $dto->completed
                ? max(0, 1200 - ($elapsedSeconds * 6) - ($normalizedMoves * 4) - ($normalizedMistakes * 15))
                : 0,
            'completed' => $dto->completed,
            'time_ms' => $timeMs,
            'moves' => $normalizedMoves,
            'mistakes' => $normalizedMistakes,
            'accuracy' => $accuracy,
            'metadata' => array_merge($dto->metadata, [
                'pairsCount' => $session->eventGame->settings_json['pairsCount'] ?? $dto->metadata['pairsCount'] ?? null,
                'difficulty' => $session->eventGame->settings_json['difficulty'] ?? $dto->metadata['difficulty'] ?? null,
                'scoringVersion' => 'memory_v1',
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
