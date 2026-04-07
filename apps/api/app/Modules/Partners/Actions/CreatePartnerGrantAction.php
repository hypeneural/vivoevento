<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Enums\EventAccessGrantStatus;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Events\Models\Event;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use Illuminate\Validation\ValidationException;

class CreatePartnerGrantAction
{
    public function __construct(
        private readonly RebuildPartnerStatsAction $rebuildPartnerStats,
    ) {}

    public function execute(Organization $partner, array $data, User $actor): EventAccessGrant
    {
        $event = Event::query()->findOrFail($data['event_id']);

        if ($event->organization_id !== $partner->id) {
            throw ValidationException::withMessages([
                'event_id' => 'O evento informado nao pertence ao parceiro selecionado.',
            ]);
        }

        $sourceType = EventAccessGrantSourceType::from($data['source_type']);

        $grant = EventAccessGrant::query()->create([
            'organization_id' => $partner->id,
            'event_id' => $event->id,
            'source_type' => $sourceType->value,
            'status' => EventAccessGrantStatus::Active->value,
            'priority' => $sourceType->defaultPriority(),
            'merge_strategy' => EntitlementMergeStrategy::Expand->value,
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? null,
            'features_snapshot_json' => $data['features'] ?? [],
            'limits_snapshot_json' => $data['limits'] ?? [],
            'granted_by_user_id' => $actor->id,
            'notes' => $data['reason'] ?? null,
            'metadata_json' => [],
        ]);

        $this->rebuildPartnerStats->execute($partner->fresh(['subscriptions.plan']));

        activity()
            ->event('partner.grant.created')
            ->performedOn($partner)
            ->causedBy($actor)
            ->withProperties([
                'partner_id' => $partner->id,
                'organization_id' => $partner->id,
                'event_id' => $event->id,
                'grant_id' => $grant->id,
                'source_type' => $grant->source_type?->value ?? $grant->source_type,
            ])
            ->log('Grant do parceiro criado');

        return $grant->load(['event:id,title,slug', 'grantedBy:id,name,email']);
    }
}
