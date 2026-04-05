<?php

namespace App\Modules\FaceSearch\DTOs;

final class DetectedFaceData
{
    public function __construct(
        public readonly FaceBoundingBoxData $boundingBox,
        public readonly float $detectionConfidence = 0.0,
        public readonly float $qualityScore = 0.0,
        public readonly ?float $sharpnessScore = null,
        public readonly ?float $faceAreaRatio = null,
        public readonly ?float $poseYaw = null,
        public readonly ?float $posePitch = null,
        public readonly ?float $poseRoll = null,
        public readonly bool $isPrimaryCandidate = false,
    ) {}
}
