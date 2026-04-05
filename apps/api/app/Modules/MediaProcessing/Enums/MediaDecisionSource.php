<?php

namespace App\Modules\MediaProcessing\Enums;

enum MediaDecisionSource: string
{
    case NoneMode = 'none_mode';
    case ManualReview = 'manual_review';
    case AiSafety = 'ai_safety';
    case AiVlm = 'ai_vlm';
    case UserOverride = 'user_override';
}
