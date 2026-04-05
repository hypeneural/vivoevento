<?php

namespace App\Modules\Clients\Actions;

use App\Modules\Clients\Models\Client;
use App\Modules\Users\Models\User;
use Illuminate\Validation\ValidationException;

class CreateClientAction
{
    public function execute(array $attributes, User $user): Client
    {
        $organizationId = $attributes['organization_id'] ?? null;

        if (! $user->hasAnyRole(['super-admin', 'platform-admin'])) {
            $organizationId = $user->currentOrganization()?->id;
        }

        if (! $organizationId) {
            throw ValidationException::withMessages([
                'organization_id' => ['Selecione uma organização válida para cadastrar o cliente.'],
            ]);
        }

        return Client::create([
            ...$attributes,
            'organization_id' => $organizationId,
            'created_by' => $user->id,
        ]);
    }
}
