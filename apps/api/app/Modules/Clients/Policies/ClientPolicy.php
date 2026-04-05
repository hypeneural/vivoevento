<?php

namespace App\Modules\Clients\Policies;

use App\Modules\Clients\Models\Client;
use App\Modules\Users\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can('clients.view') && $this->canAccessClient($user, $client);
    }

    public function create(User $user): bool
    {
        return $user->can('clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can('clients.update') && $this->canAccessClient($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can('clients.delete') && $this->canAccessClient($user, $client);
    }

    private function canAccessClient(User $user, Client $client): bool
    {
        if ($user->hasAnyRole(['super-admin', 'platform-admin'])) {
            return true;
        }

        return $user->currentOrganization()?->id === $client->organization_id;
    }
}
