<?php

namespace App\Modules\MediaIntelligence\Enums;

enum VisualReasoningDecision: string
{
    case Approve = 'approve';
    case Review = 'review';
    case Reject = 'reject';
    case Skipped = 'skipped';
}
