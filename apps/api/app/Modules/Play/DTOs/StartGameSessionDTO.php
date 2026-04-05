<?php

namespace App\Modules\Play\DTOs;

final readonly class StartGameSessionDTO
{
    public function __construct(
        public int $eventGameId,
        public string $playerIdentifier,
        public ?string $playerName = null,
        public ?array $device = null,
    ) {}
}
