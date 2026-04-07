<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceQualityAssessmentData;
use App\Modules\FaceSearch\Enums\FaceQualityTier;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;

class FaceQualityGateService
{
    public function assess(
        DetectedFaceData $face,
        EventFaceSearchSetting $settings,
    ): FaceQualityAssessmentData {
        $minFaceSide = min($face->boundingBox->width, $face->boundingBox->height);

        if ($minFaceSide < $settings->min_face_size_px) {
            return new FaceQualityAssessmentData(
                tier: FaceQualityTier::Reject,
                reason: 'face_too_small',
            );
        }

        if ($face->qualityScore < $settings->min_quality_score) {
            return new FaceQualityAssessmentData(
                tier: FaceQualityTier::Reject,
                reason: 'low_quality',
            );
        }

        $priorityMinFaceSide = (int) ceil(
            $settings->min_face_size_px * (float) config('face_search.quality.search_priority_face_size_multiplier', 1.35),
        );
        $priorityMinQualityScore = min(
            1.0,
            $settings->min_quality_score + (float) config('face_search.quality.search_priority_quality_delta', 0.10),
        );

        if ($minFaceSide >= $priorityMinFaceSide && $face->qualityScore >= $priorityMinQualityScore) {
            return new FaceQualityAssessmentData(
                tier: FaceQualityTier::SearchPriority,
            );
        }

        return new FaceQualityAssessmentData(
            tier: FaceQualityTier::IndexOnly,
            reason: $minFaceSide < $priorityMinFaceSide ? 'borderline_face_size' : 'borderline_quality',
        );
    }

    public function passes(
        DetectedFaceData $face,
        EventFaceSearchSetting $settings,
    ): bool {
        return ! $this->assess($face, $settings)->isRejected();
    }
}
