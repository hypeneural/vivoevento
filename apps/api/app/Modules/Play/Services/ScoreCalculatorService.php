<?php

namespace App\Modules\Play\Services;

use App\Modules\Play\DTOs\FinishGameSessionDTO;
use App\Modules\Play\Enums\PlayGameTypeKey;
use App\Modules\Play\Models\PlayGameSession;
use App\Modules\Play\Support\GameScoreCalculatorInterface;
use App\Modules\Play\Support\MemoryScoreCalculator;
use App\Modules\Play\Support\PuzzleScoreCalculator;

class ScoreCalculatorService
{
    public function __construct(
        private readonly MemoryScoreCalculator $memoryCalculator,
        private readonly PuzzleScoreCalculator $puzzleCalculator,
    ) {}

    public function normalize(PlayGameSession $session, FinishGameSessionDTO $dto): array
    {
        $session->loadMissing('eventGame.gameType');

        $gameTypeKey = $session->eventGame?->gameType?->key?->value ?? $session->eventGame?->gameType?->key;

        return $this->calculatorFor((string) $gameTypeKey)->calculate($session, $dto);
    }

    private function calculatorFor(string $gameTypeKey): GameScoreCalculatorInterface
    {
        return match ($gameTypeKey) {
            PlayGameTypeKey::Memory->value => $this->memoryCalculator,
            PlayGameTypeKey::Puzzle->value => $this->puzzleCalculator,
            default => $this->memoryCalculator,
        };
    }
}
