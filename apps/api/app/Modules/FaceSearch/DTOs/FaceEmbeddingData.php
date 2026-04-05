<?php

namespace App\Modules\FaceSearch\DTOs;

final class FaceEmbeddingData
{
    /**
     * @param array<int, float> $vector
     */
    public function __construct(
        public readonly array $vector,
        public readonly ?string $providerKey = null,
        public readonly ?string $providerVersion = null,
        public readonly ?string $modelKey = null,
        public readonly ?string $modelSnapshot = null,
        public readonly ?string $embeddingVersion = null,
        public readonly array $rawResponse = [],
    ) {}
}
