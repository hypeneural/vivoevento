<?php

namespace App\Modules\Notifications\Enums;

enum NotificationStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
