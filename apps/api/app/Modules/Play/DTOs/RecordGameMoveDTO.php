<?php

namespace App\Modules\Play\DTOs;

final readonly class RecordGameMoveDTO
{
    public function __construct(
        public int $moveNumber,
        public string $moveType,
        public array $payload = [],
        public ?string $occurredAt = null,
    ) {}
}
