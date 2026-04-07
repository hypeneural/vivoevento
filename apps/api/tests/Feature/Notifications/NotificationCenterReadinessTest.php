<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Organizations\Models\OrganizationMember;

it('surfaces dashboard alert signals that can seed the phase-1 notification snapshot', function () {
    [$user, $organization] = $this->actingAsOwner();

    $nearLimitEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento no limite',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDays(2),
        'purchased_plan_snapshot_json' => [
            'max_photos' => 10,
        ],
    ]);

    EventMedia::factory()->count(9)->create([
        'event_id' => $nearLimitEvent->id,
    ]);

    $errorEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento com erro',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDays(2),
    ]);

    EventMedia::factory()->create([
        'event_id' => $errorEvent->id,
        'processing_status' => MediaProcessingStatus::Failed->value,
    ]);

    $startsTodayWithoutMedia = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento hoje sem midia',
        'starts_at' => now()->startOfDay()->addHours(18),
        'ends_at' => now()->addDay(),
    ]);

    $response = $this->apiGet('/dashboard/stats');

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.kpis.processing_errors', 1);

    $alerts = collect($response->json('data.alerts'));

    expect($alerts->contains(fn (array $alert) => $alert['type'] === 'warning'
        && $alert['entity_type'] === 'event'
        && $alert['entity_id'] === $nearLimitEvent->id
        && str_contains($alert['message'], 'limite de fotos')))->toBeTrue();

    expect($alerts->contains(fn (array $alert) => $alert['type'] === 'error'
        && $alert['entity_type'] === 'media'
        && str_contains($alert['message'], 'erro de processamento')))->toBeTrue();

    expect($alerts->contains(fn (array $alert) => $alert['type'] === 'info'
        && $alert['entity_type'] === 'event'
        && $alert['entity_id'] === $startsTodayWithoutMedia->id
        && str_contains($alert['message'], $startsTodayWithoutMedia->title)))->toBeTrue();
});

it('characterizes that the finance role still lacks inbox permission even though it is a likely billing recipient', function () {
    $this->seedPermissions();

    $organization = $this->createOrganization();
    $user = $this->createUser();

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role_key' => 'financeiro',
        'is_owner' => false,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $user->assignRole('financeiro');

    expect($user->can('billing.view'))->toBeTrue()
        ->and($user->can('billing.manage_subscription'))->toBeTrue()
        ->and($user->can('notifications.view'))->toBeFalse();
});
