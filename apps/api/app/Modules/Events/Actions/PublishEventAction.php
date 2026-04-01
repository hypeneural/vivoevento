<?php

namespace App\Modules\Events\Actions;

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;

class PublishEventAction
{
    public function execute(Event $event): Event
    {
        $event->update(['status' => EventStatus::Active]);

        return $event->fresh();
    }
}
