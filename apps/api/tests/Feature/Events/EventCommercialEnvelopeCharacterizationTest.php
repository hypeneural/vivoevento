<?php

use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Events\Models\Event;
use App\Modules\Plans\Models\Plan;

it('still allows the raw event retention_days to diverge above the resolved commercial retention limit', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'retention_days' => 30,
    ]);

    EventAccessGrant::factory()
        ->forEvent($event)
        ->create([
            'granted_by_user_id' => $user->id,
            'source_type' => EventAccessGrantSourceType::Bonus->value,
            'merge_strategy' => EntitlementMergeStrategy::Replace->value,
            'priority' => EventAccessGrantSourceType::Bonus->defaultPriority(),
            'limits_snapshot_json' => [
                'media.retention_days' => 30,
            ],
        ]);

    $response = $this->apiPatch("/events/{$event->id}", [
        'privacy' => [
            'retention_days' => 365,
        ],
    ]);

    $this->assertApiSuccess($response);

    $event->refresh();

    expect($event->retention_days)->toBe(365)
        ->and(data_get($event->current_entitlements_json, 'limits.retention_days'))->toBe(30);
});

it('still allows switching the event to ai moderation without a commercial ai entitlement', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'moderation_mode' => 'manual',
    ]);

    $response = $this->apiPatch("/events/{$event->id}", [
        'privacy' => [
            'moderation_mode' => 'ai',
        ],
    ]);

    $this->assertApiSuccess($response);

    $event->refresh();

    expect($event->moderation_mode?->value)->toBe('ai');
});

it('still allows enabling event modules that exceed the resolved commercial entitlement baseline', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = Plan::create([
        'code' => 'no-wall',
        'name' => 'No Wall',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'false'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'false'],
    ]);

    Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}", [
        'modules' => [
            'wall' => true,
            'play' => true,
        ],
    ]);

    $this->assertApiSuccess($response);

    $event->refresh()->load('modules');

    expect($event->isModuleEnabled('wall'))->toBeTrue()
        ->and($event->isModuleEnabled('play'))->toBeTrue()
        ->and(data_get($event->current_entitlements_json, 'modules.wall'))->toBeFalse()
        ->and(data_get($event->current_entitlements_json, 'modules.play'))->toBeFalse();
});
