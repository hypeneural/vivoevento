<?php

namespace App\Modules\FaceSearch\DTOs;

final class FaceSearchMatchData
{
    public function __construct(
        public readonly int $faceId,
        public readonly int $eventMediaId,
        public readonly float $distance,
        public readonly ?float $qualityScore = null,
        public readonly ?float $faceAreaRatio = null,
        public readonly ?string $qualityTier = null,
    ) {}
}
