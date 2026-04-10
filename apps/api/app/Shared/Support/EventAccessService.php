<?php

namespace App\Shared\Support;

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use App\Modules\Users\Models\User;

class EventAccessService
{
    public function __construct(
        private readonly EventAccessPresetRegistry $presetRegistry,
    ) {}

    public function can(User $user, Event|int $event, string $permission): bool
    {
        $eventModel = $event instanceof Event
            ? $event
            : Event::query()->find($event);

        if (! $eventModel) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'platform-admin'])) {
            return true;
        }

        $hasOrganizationAccess = $user->can($permission)
            && $user->organizationMembers()
            ->active()
            ->where('organization_id', $eventModel->organization_id)
            ->exists();

        if ($hasOrganizationAccess) {
            return true;
        }

        $eventRole = $user->eventTeamMembers()
            ->where('event_id', $eventModel->id)
            ->value('role');

        if (! is_string($eventRole) || $eventRole === '') {
            return false;
        }

        return in_array(
            $permission,
            $this->presetRegistry->permissionsForPersistedRole($eventRole),
            true,
        );
    }
}
