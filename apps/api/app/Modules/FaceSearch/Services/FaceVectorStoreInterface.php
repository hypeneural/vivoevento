<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Models\EventMediaFace;

interface FaceVectorStoreInterface
{
    public function upsert(EventMediaFace $face, FaceEmbeddingData $embedding): EventMediaFace;

    public function delete(EventMediaFace $face): void;

    /**
     * @param array<int, float> $queryEmbedding
     * @return array<int, FaceSearchMatchData>
     */
    public function search(
        int $eventId,
        array $queryEmbedding,
        int $topK,
        ?float $threshold = null,
        bool $searchableOnly = true,
        ?string $searchStrategy = null,
    ): array;
}
