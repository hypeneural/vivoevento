<?php

namespace App\Modules\Wall\Policies;

use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use App\Shared\Support\EventAccessService;

class WallPolicy
{
    public function __construct(
        private readonly EventAccessService $eventAccess,
    ) {}

    public function view(User $user, Event $event): bool
    {
        return $this->eventAccess->can($user, $event, 'wall.view');
    }

    public function manage(User $user, Event $event): bool
    {
        return $this->eventAccess->can($user, $event, 'wall.manage');
    }
}
