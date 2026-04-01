<?php

namespace App\Modules\Events\Actions;

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;

class ArchiveEventAction
{
    public function execute(Event $event): Event
    {
        $event->update(['status' => EventStatus::Archived]);

        return $event->fresh();
    }
}
