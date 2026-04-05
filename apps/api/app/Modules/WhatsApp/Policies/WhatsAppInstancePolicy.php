<?php

namespace App\Modules\WhatsApp\Policies;

use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppInstance;

class WhatsAppInstancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('channels.view') || $user->can('channels.manage');
    }

    public function view(User $user, WhatsAppInstance $instance): bool
    {
        return ($user->can('channels.view') || $user->can('channels.manage'))
            && $this->withinOrganization($user, $instance);
    }

    public function create(User $user): bool
    {
        return $user->can('channels.manage');
    }

    public function update(User $user, WhatsAppInstance $instance): bool
    {
        return $user->can('channels.manage')
            && $this->withinOrganization($user, $instance);
    }

    public function delete(User $user, WhatsAppInstance $instance): bool
    {
        return $user->can('channels.manage')
            && $this->withinOrganization($user, $instance);
    }

    private function withinOrganization(User $user, WhatsAppInstance $instance): bool
    {
        if ($user->hasAnyRole(['super-admin', 'platform-admin'])) {
            return true;
        }

        return ($user->currentOrganization()?->id ?? $user->current_organization_id) === $instance->organization_id;
    }
}
