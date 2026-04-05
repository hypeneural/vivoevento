<?php

namespace App\Modules\Play\DTOs;

final readonly class HeartbeatGameSessionDTO
{
    public function __construct(
        public string $sessionUuid,
        public string $state,
        public ?string $reason = null,
        public ?int $elapsedMs = null,
    ) {}
}
