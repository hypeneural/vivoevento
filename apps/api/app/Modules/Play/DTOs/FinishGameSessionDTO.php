<?php

namespace App\Modules\Play\DTOs;

final readonly class FinishGameSessionDTO
{
    public function __construct(
        public string $sessionUuid,
        public int $score,
        public bool $completed,
        public int $timeMs,
        public int $moves,
        public ?int $mistakes = null,
        public ?float $accuracy = null,
        public array $metadata = [],
    ) {}
}
