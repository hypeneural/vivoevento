<?php

namespace App\Modules\Play\DTOs;

final readonly class GameAssetDTO
{
    public function __construct(
        public int $mediaId,
        public string $role,
        public int $sortOrder = 0,
        public array $metadata = [],
    ) {}
}
