<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Models\EventMedia;

interface VisualReasoningProviderInterface
{
    public function evaluate(
        EventMedia $media,
        EventMediaIntelligenceSetting $settings,
    ): VisualReasoningEvaluationResult;
}
