<?php

namespace App\Modules\ContentModeration\Services;

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\MediaProcessing\Models\EventMedia;

interface ContentModerationProviderInterface
{
    public function evaluate(
        EventMedia $media,
        EventContentModerationSetting $settings,
    ): ContentSafetyEvaluationResult;
}
