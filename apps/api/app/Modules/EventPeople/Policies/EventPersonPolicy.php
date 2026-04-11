<?php

namespace App\Modules\EventPeople\Policies;

use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\Users\Models\User;
use App\Shared\Support\EventAccessService;

class EventPersonPolicy
{
    public function __construct(
        private readonly EventAccessService $eventAccess,
    ) {}

    public function view(User $user, EventPerson $person): bool
    {
        return $this->eventAccess->can($user, $person->event, 'events.view');
    }

    public function update(User $user, EventPerson $person): bool
    {
        return $this->eventAccess->can($user, $person->event, 'events.update');
    }
}
