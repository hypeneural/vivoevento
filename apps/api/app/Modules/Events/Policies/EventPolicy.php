<?php

namespace App\Modules\Events\Policies;

use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('events.view');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->can('events.view');
    }

    public function create(User $user): bool
    {
        return $user->can('events.create');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can('events.update');
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->can('events.publish');
    }

    public function archive(User $user, Event $event): bool
    {
        return $user->can('events.archive');
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->can('events.archive');
    }
}
