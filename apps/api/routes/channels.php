<?php

use App\Modules\Users\Models\User;
use App\Shared\Support\EventAccessService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('wall.{wallCode}', function () {
    return true;
});

Broadcast::channel('play.game.{gameUuid}', function () {
    return true;
});

$authorizeEventChannel = function (User $user, int $eventId, string $permission): bool {
    return app(EventAccessService::class)->can($user, $eventId, $permission);
};

Broadcast::channel('event.{eventId}.wall', function ($user, int $eventId) use ($authorizeEventChannel) {
    return $user instanceof User
        && $authorizeEventChannel($user, $eventId, 'wall.view');
});

Broadcast::channel('event.{eventId}.gallery', function ($user, int $eventId) use ($authorizeEventChannel) {
    return $user instanceof User
        && $authorizeEventChannel($user, $eventId, 'gallery.view');
});

Broadcast::channel('event.{eventId}.moderation', function ($user, int $eventId) use ($authorizeEventChannel) {
    return $user instanceof User
        && $authorizeEventChannel($user, $eventId, 'media.moderate');
});

Broadcast::channel('organization.{organizationId}.moderation', function ($user, int $organizationId) {
    if (! $user instanceof User) {
        return false;
    }

    if (! $user->can('media.view') && ! $user->can('media.moderate')) {
        return false;
    }

    if ($user->hasAnyRole(['super-admin', 'platform-admin'])) {
        return true;
    }

    return $user->organizationMembers()
        ->active()
        ->where('organization_id', $organizationId)
        ->exists();
});

Broadcast::channel('user.{userId}.notifications', function ($user, int $userId) {
    return $user instanceof User
        && $user->id === $userId
        && $user->can('notifications.view');
});

Broadcast::channel('event.{eventId}.play', function ($user, int $eventId) use ($authorizeEventChannel) {
    return $user instanceof User
        && $authorizeEventChannel($user, $eventId, 'play.view');
});
