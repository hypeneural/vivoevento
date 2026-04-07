<?php

namespace App\Modules\FaceSearch\DTOs;

use App\Modules\FaceSearch\Enums\FaceQualityTier;

final class FaceQualityAssessmentData
{
    public function __construct(
        public readonly FaceQualityTier $tier,
        public readonly ?string $reason = null,
    ) {}

    public function isRejected(): bool
    {
        return $this->tier === FaceQualityTier::Reject;
    }
}
