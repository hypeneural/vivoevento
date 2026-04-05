<?php

namespace App\Modules\Play\Services;

use App\Modules\Play\Enums\PlayGameTypeKey;
use App\Modules\Play\Support\GameSettingsValidatorInterface;
use App\Modules\Play\Support\MemorySettingsValidator;
use App\Modules\Play\Support\PuzzleSettingsValidator;

class GameSettingsValidationService
{
    public function __construct(
        private readonly MemorySettingsValidator $memoryValidator,
        private readonly PuzzleSettingsValidator $puzzleValidator,
    ) {}

    public function validateForType(string $gameTypeKey, array $settings): array
    {
        return $this->validatorFor($gameTypeKey)->validate($settings);
    }

    private function validatorFor(string $gameTypeKey): GameSettingsValidatorInterface
    {
        return match ($gameTypeKey) {
            PlayGameTypeKey::Memory->value => $this->memoryValidator,
            PlayGameTypeKey::Puzzle->value => $this->puzzleValidator,
            default => throw new \InvalidArgumentException("Tipo de jogo nao suportado: {$gameTypeKey}"),
        };
    }
}
