<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;

class FaceQualityGateService
{
    public function passes(
        DetectedFaceData $face,
        EventFaceSearchSetting $settings,
    ): bool {
        return $face->boundingBox->width >= $settings->min_face_size_px
            && $face->boundingBox->height >= $settings->min_face_size_px
            && $face->qualityScore >= $settings->min_quality_score;
    }
}
