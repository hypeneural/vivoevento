<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonRepresentativeSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
