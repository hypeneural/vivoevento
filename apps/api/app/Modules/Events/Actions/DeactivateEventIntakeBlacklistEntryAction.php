<?php

namespace App\Modules\Events\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventMediaSenderBlacklist;

class DeactivateEventIntakeBlacklistEntryAction
{
    public function execute(Event $event, EventMediaSenderBlacklist $entry): EventMediaSenderBlacklist
    {
        abort_unless($entry->event_id === $event->id, 404);

        $entry->forceFill([
            'is_active' => false,
        ])->save();

        return $entry->refresh();
    }
}
