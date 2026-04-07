<?php

namespace App\Modules\Events\Http\Controllers;

use App\Modules\Events\Actions\DeactivateEventIntakeBlacklistEntryAction;
use App\Modules\Events\Actions\UpsertEventIntakeBlacklistEntryAction;
use App\Modules\Events\Http\Requests\UpsertEventIntakeBlacklistEntryRequest;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\Events\Support\EventIntakeBlacklistStateBuilder;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventIntakeBlacklistController extends BaseController
{
    public function store(
        UpsertEventIntakeBlacklistEntryRequest $request,
        Event $event,
        UpsertEventIntakeBlacklistEntryAction $action,
        EventIntakeBlacklistStateBuilder $stateBuilder,
    ): JsonResponse {
        $this->authorize('update', $event);

        $entry = $action->execute($event, $request->validated());
        $state = $stateBuilder->build($event->fresh(['mediaSenderBlacklists']));

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties([
                'event_id' => $event->id,
                'blacklist_entry_id' => $entry->id,
                'identity_type' => $entry->identity_type,
                'identity_value' => $entry->identity_value,
                'is_active' => $entry->is_active,
            ])
            ->log('events.intake_blacklist.entry.upserted');

        return $this->success([
            'entry_id' => $entry->id,
            'intake_blacklist' => $state['intake_blacklist'],
        ]);
    }

    public function destroy(
        Request $request,
        Event $event,
        EventMediaSenderBlacklist $entry,
        DeactivateEventIntakeBlacklistEntryAction $action,
        EventIntakeBlacklistStateBuilder $stateBuilder,
    ): JsonResponse {
        $this->authorize('update', $event);

        $entry = $action->execute($event, $entry);
        $state = $stateBuilder->build($event->fresh(['mediaSenderBlacklists']));

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties([
                'event_id' => $event->id,
                'blacklist_entry_id' => $entry->id,
                'identity_type' => $entry->identity_type,
                'identity_value' => $entry->identity_value,
            ])
            ->log('events.intake_blacklist.entry.deactivated');

        return $this->success([
            'entry_id' => $entry->id,
            'intake_blacklist' => $state['intake_blacklist'],
        ]);
    }
}
