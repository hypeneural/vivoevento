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
        return $this->assessAgainstThresholds(
            face: $face,
            minFaceSizePx: $settings->min_face_size_px,
            minQualityScore: $settings->min_quality_score,
            searchPriorityFaceSizeMultiplier: (float) config('face_search.quality.search_priority_face_size_multiplier', 1.35),
            searchPriorityQualityDelta: (float) config('face_search.quality.search_priority_quality_delta', 0.10),
        );
    }

    public function assessAwsIndex(
        DetectedFaceData $face,
        EventFaceSearchSetting $settings,
        string $sourceKind = 'source_image',
    ): FaceQualityAssessmentData {
        if ($sourceKind !== 'face_crop') {
            return $this->assess($face, $settings);
        }

        $profileKey = is_string($settings->aws_index_profile_key) && $settings->aws_index_profile_key !== ''
            ? $settings->aws_index_profile_key
            : 'default';
        $profile = (array) config(
            "face_search.providers.aws_rekognition.index_profiles.{$profileKey}",
            config('face_search.providers.aws_rekognition.index_profiles.default', []),
        );
        $cropMinQualityScore = $profile['crop_min_quality_score'] ?? null;
        $searchPriorityQualityDelta = $profile['crop_search_priority_quality_delta']
            ?? config('face_search.quality.search_priority_quality_delta', 0.10);

        return $this->assessAgainstThresholds(
            face: $face,
            minFaceSizePx: $settings->min_face_size_px,
            minQualityScore: is_numeric($cropMinQualityScore)
                ? (float) $cropMinQualityScore
                : $settings->min_quality_score,
            searchPriorityFaceSizeMultiplier: (float) config('face_search.quality.search_priority_face_size_multiplier', 1.35),
            searchPriorityQualityDelta: (float) $searchPriorityQualityDelta,
        );
    }

    public function passes(
        DetectedFaceData $face,
        EventFaceSearchSetting $settings,
    ): bool {
        return ! $this->assess($face, $settings)->isRejected();
    }

    private function assessAgainstThresholds(
        DetectedFaceData $face,
        int $minFaceSizePx,
        float $minQualityScore,
        float $searchPriorityFaceSizeMultiplier,
        float $searchPriorityQualityDelta,
    ): FaceQualityAssessmentData {
        $minFaceSide = min($face->boundingBox->width, $face->boundingBox->height);

        if ($minFaceSide < $minFaceSizePx) {
            return new FaceQualityAssessmentData(
                tier: FaceQualityTier::Reject,
                reason: 'face_too_small',
            );
        }

        if ($face->qualityScore < $minQualityScore) {
            return new FaceQualityAssessmentData(
                tier: FaceQualityTier::Reject,
                reason: 'low_quality',
            );
        }

        $priorityMinFaceSide = (int) ceil(
            $minFaceSizePx * $searchPriorityFaceSizeMultiplier,
        );
        $priorityMinQualityScore = min(
            1.0,
            $minQualityScore + $searchPriorityQualityDelta,
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
}
