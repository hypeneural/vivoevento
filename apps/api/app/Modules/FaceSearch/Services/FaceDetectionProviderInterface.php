<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;

interface FaceDetectionProviderInterface
{
    /**
     * @return array<int, DetectedFaceData>
     */
    public function detect(
        EventMedia $media,
        EventFaceSearchSetting $settings,
        string $binary,
    ): array;
}
