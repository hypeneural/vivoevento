<?php

namespace App\Modules\Clients\Actions;

use App\Modules\Clients\Models\Client;

class UpdateClientAction
{
    public function execute(Client $client, array $attributes): Client
    {
        unset($attributes['organization_id'], $attributes['created_by']);

        $client->update($attributes);

        return $client->fresh([
            'organization.subscription.plan',
        ]);
    }
}
