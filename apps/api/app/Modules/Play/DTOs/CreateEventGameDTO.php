<?php

namespace App\Modules\Play\DTOs;

final readonly class CreateEventGameDTO
{
    public function __construct(
        public int $eventId,
        public string $gameTypeKey,
        public string $title,
        public ?string $slug,
        public bool $rankingEnabled,
        public bool $isActive,
        public int $sortOrder,
        public array $settings,
    ) {}
}
