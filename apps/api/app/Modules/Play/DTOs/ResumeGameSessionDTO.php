<?php

namespace App\Modules\Play\DTOs;

final readonly class ResumeGameSessionDTO
{
    public function __construct(
        public string $sessionUuid,
        public string $resumeToken,
    ) {}
}
