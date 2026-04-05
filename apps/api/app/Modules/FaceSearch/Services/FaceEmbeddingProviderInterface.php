<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;

interface FaceEmbeddingProviderInterface
{
    public function embed(
        EventMedia $media,
        EventFaceSearchSetting $settings,
        string $cropBinary,
        DetectedFaceData $face,
    ): FaceEmbeddingData;
}
