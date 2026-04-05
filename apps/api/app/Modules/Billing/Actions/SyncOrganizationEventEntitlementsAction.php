<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Organizations\Models\Organization;

class SyncOrganizationEventEntitlementsAction
{
    public function __construct(
        private readonly SyncEventEntitlementsAction $syncEventEntitlements,
    ) {}

    public function execute(Organization|int|null $organization, ?string $reason = null): int
    {
        $organizationId = match (true) {
            $organization instanceof Organization => $organization->id,
            is_int($organization) => $organization,
            default => null,
        };

        if (! $organizationId) {
            return 0;
        }

        $count = 0;

        Event::query()
            ->where('organization_id', $organizationId)
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($events) use (&$count, $reason) {
                foreach ($events as $event) {
                    $this->syncEventEntitlements->execute((int) $event->id, $reason);
                    $count++;
                }
            });

        return $count;
    }
}
