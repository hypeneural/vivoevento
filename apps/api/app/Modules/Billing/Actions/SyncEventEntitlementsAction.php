<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Support\EventCommercialStatusService;

class SyncEventEntitlementsAction
{
    public function __construct(
        private readonly EventCommercialStatusService $commercialStatus,
    ) {}

    public function execute(Event|int|null $event, ?string $reason = null): ?Event
    {
        $eventModel = match (true) {
            $event instanceof Event => $event,
            is_int($event) => Event::query()->find($event),
            default => null,
        };

        if (! $eventModel) {
            return null;
        }

        $beforeMode = $eventModel->commercial_mode?->value;
        $beforeEntitlements = $eventModel->current_entitlements_json;

        $eventModel = $this->commercialStatus->sync($eventModel);

        $afterMode = $eventModel->commercial_mode?->value;
        $afterEntitlements = $eventModel->current_entitlements_json;

        if ($beforeMode !== $afterMode || $this->payloadChanged($beforeEntitlements, $afterEntitlements)) {
            activity()
                ->performedOn($eventModel)
                ->withProperties([
                    'reason' => $reason,
                    'previous_commercial_mode' => $beforeMode,
                    'commercial_mode' => $afterMode,
                    'current_entitlements' => $afterEntitlements,
                ])
                ->log('Entitlements comerciais recalculados');
        }

        return $eventModel;
    }

    private function payloadChanged(mixed $before, mixed $after): bool
    {
        return json_encode($before, JSON_THROW_ON_ERROR) !== json_encode($after, JSON_THROW_ON_ERROR);
    }
}
