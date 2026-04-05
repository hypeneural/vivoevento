<?php

namespace App\Modules\Events\Support;

use App\Modules\Billing\Services\EntitlementResolverService;
use App\Modules\Events\Models\Event;

class EventCommercialStatusService
{
    public function __construct(
        private readonly EntitlementResolverService $resolver,
    ) {}

    public function sync(Event $event): Event
    {
        $status = $this->build($event);

        $event->forceFill([
            'commercial_mode' => $status['commercial_mode'],
            'current_entitlements_json' => $status['resolved_entitlements'],
        ])->save();

        return $event->fresh();
    }

    public function build(Event $event): array
    {
        return $this->resolver->resolve($event);
    }
}
