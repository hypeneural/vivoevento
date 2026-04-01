<?php

namespace App\Modules\Events\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';
    case Archived = 'archived';
}
