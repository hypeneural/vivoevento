<?php

namespace App\Shared\Support;

use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;

class EventAccessService
{
    public function can(User $user, Event|int $event, string $permission): bool
    {
        $eventModel = $event instanceof Event
            ? $event
            : Event::query()->find($event);

        if (! $eventModel) {
            return false;
        }

        if (! $user->can($permission)) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'platform-admin'])) {
            return true;
        }

        return $user->organizationMembers()
            ->active()
            ->where('organization_id', $eventModel->organization_id)
            ->exists();
    }
}
