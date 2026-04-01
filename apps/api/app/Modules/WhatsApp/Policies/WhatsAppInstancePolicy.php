<?php

namespace App\Modules\WhatsApp\Policies;

use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppInstance;

class WhatsAppInstancePolicy
{
    /**
     * Instances belong to an organization — user must be a member.
     */
    public function viewAny(User $user): bool
    {
        return true; // Scoped by organization in controller
    }

    public function view(User $user, WhatsAppInstance $instance): bool
    {
        return $user->current_organization_id === $instance->organization_id;
    }

    public function create(User $user): bool
    {
        return true; // Permission check: whatsapp.manage
    }

    public function update(User $user, WhatsAppInstance $instance): bool
    {
        return $user->current_organization_id === $instance->organization_id;
    }

    public function delete(User $user, WhatsAppInstance $instance): bool
    {
        return $user->current_organization_id === $instance->organization_id;
    }
}
