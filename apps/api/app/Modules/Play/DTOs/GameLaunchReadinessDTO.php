<?php

namespace App\Modules\Play\DTOs;

final readonly class GameLaunchReadinessDTO
{
    public function __construct(
        public bool $published,
        public bool $launchable,
        public bool $bootable,
        public ?string $reason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'published' => $this->published,
            'launchable' => $this->launchable,
            'bootable' => $this->bootable,
            'reason' => $this->reason,
        ];
    }
}
