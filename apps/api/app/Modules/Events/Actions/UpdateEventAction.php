<?php

namespace App\Modules\Events\Actions;

use App\Modules\Events\Models\Event;

class UpdateEventAction
{
    public function execute(Event $event, array $data): Event
    {
        $event->update($data);

        return $event->fresh();
    }
}
