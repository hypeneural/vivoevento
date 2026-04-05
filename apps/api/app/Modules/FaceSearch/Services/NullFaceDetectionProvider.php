<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;

class NullFaceDetectionProvider implements FaceDetectionProviderInterface
{
    public function detect(
        EventMedia $media,
        EventFaceSearchSetting $settings,
        string $binary,
    ): array {
        return [];
    }
}
