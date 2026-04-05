<?php

namespace App\Modules\ContentModeration\Enums;

enum ContentSafetyDecision: string
{
    case Pass = 'pass';
    case Review = 'review';
    case Block = 'block';
    case Skipped = 'skipped';
}
