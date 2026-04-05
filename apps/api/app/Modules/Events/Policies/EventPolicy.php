<?php

namespace App\Modules\Events\Policies;

use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use App\Shared\Support\EventAccessService;

class EventPolicy
{
    public function __construct(
        private readonly EventAccessService $eventAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('events.view');
    }

    public function view(User $user, Event $event): bool
    {
        return $this->eventAccess->can($user, $event, 'events.view');
    }

    public function create(User $user): bool
    {
        return $user->can('events.create');
    }

    public function update(User $user, Event $event): bool
    {
        return $this->eventAccess->can($user, $event, 'events.update');
    }

    public function publish(User $user, Event $event): bool
    {
        return $this->eventAccess->can($user, $event, 'events.publish');
    }

    public function archive(User $user, Event $event): bool
    {
        return $this->eventAccess->can($user, $event, 'events.archive');
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->eventAccess->can($user, $event, 'events.archive');
    }
}
