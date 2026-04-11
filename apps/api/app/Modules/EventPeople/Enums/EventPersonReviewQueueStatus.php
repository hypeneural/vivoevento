<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonReviewQueueStatus: string
{
    case Pending = 'pending';
    case Conflict = 'conflict';
    case Resolved = 'resolved';
    case Ignored = 'ignored';
}
