<?php

namespace App\Modules\EventPeople\Enums;

enum EventCoverageAlertStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';
}
