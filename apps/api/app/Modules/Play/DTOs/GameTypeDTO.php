<?php

namespace App\Modules\Play\DTOs;

final readonly class GameTypeDTO
{
    public function __construct(
        public string $key,
        public string $name,
        public ?string $description,
        public bool $supportsRanking,
        public bool $supportsPhotoAssets,
        public array $configSchema,
    ) {}
}
