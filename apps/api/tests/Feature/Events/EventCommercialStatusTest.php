<?php

use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\EventPurchase;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Events\Models\Event;
use App\Modules\Plans\Models\Plan;

it('returns a commercial status summary using the organization subscription baseline', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = Plan::create([
        'code' => 'professional',
        'name' => 'Professional',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'false'],
        ['feature_key' => 'media.retention_days', 'feature_value' => '90'],
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
        'commercial_mode' => 'none',
    ]);

    $response = $this->apiGet("/events/{$event->id}/commercial-status");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.commercial_mode', 'subscription_covered');
    $response->assertJsonPath('data.subscription_summary.plan_key', 'professional');
    $response->assertJsonPath('data.resolved_entitlements.limits.retention_days', 90);
});

it('prefers a paid package-backed event purchase over the subscription when resolving the current commercial mode', function () {
    [$user, $organization] = $this->actingAsOwner();

    $subscriptionPlan = Plan::create([
        'code' => 'starter',
        'name' => 'Starter',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $subscriptionPlan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);

    $package = EventPackage::factory()->create([
        'code' => 'premium-event',
        'name' => 'Premium Event',
    ]);

    $package->prices()->create([
        'billing_mode' => 'one_time',
        'currency' => 'BRL',
        'amount_cents' => 19900,
        'is_active' => true,
        'is_default' => true,
    ]);

    $package->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'media.max_photos', 'feature_value' => '300'],
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    EventPurchase::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'plan_id' => null,
        'package_id' => $package->id,
        'price_snapshot_cents' => 19900,
        'currency' => 'BRL',
        'features_snapshot_json' => null,
        'status' => 'paid',
        'purchased_by_user_id' => $user->id,
        'purchased_at' => now(),
    ]);

    $response = $this->apiGet("/events/{$event->id}/commercial-status");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.commercial_mode', 'single_purchase');
    $response->assertJsonPath('data.purchase_summary.catalog_type', 'event_package');
    $response->assertJsonPath('data.purchase_summary.package_code', 'premium-event');
    $response->assertJsonPath('data.resolved_entitlements.limits.max_photos', 300);
});

it('returns active grants in the commercial status and persists the resolved entitlements snapshot', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    EventAccessGrant::factory()
        ->forEvent($event)
        ->create([
            'granted_by_user_id' => $user->id,
            'source_type' => EventAccessGrantSourceType::Bonus->value,
            'merge_strategy' => EntitlementMergeStrategy::Expand->value,
            'priority' => EventAccessGrantSourceType::Bonus->defaultPriority(),
            'features_snapshot_json' => [
                'wall.enabled' => true,
                'play.enabled' => true,
                'white_label.enabled' => true,
            ],
            'limits_snapshot_json' => [
                'media.retention_days' => 120,
                'media.max_photos' => 500,
            ],
        ]);

    $response = $this->apiGet("/events/{$event->id}/commercial-status");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.commercial_mode', 'bonus');
    $response->assertJsonPath('data.grants_summary.0.source_type', 'bonus');
    $response->assertJsonPath('data.resolved_entitlements.modules.play', true);
    $response->assertJsonPath('data.resolved_entitlements.branding.white_label', true);
    $response->assertJsonPath('data.resolved_entitlements.limits.max_photos', 500);

    $event->refresh();

    expect($event->commercial_mode?->value)->toBe('bonus');
    expect($event->current_entitlements_json['limits']['max_photos'] ?? null)->toBe(500);
});

it('recalculates the event snapshot automatically when a paid purchase is created', function () {
    [$user, $organization] = $this->actingAsOwner();

    $purchasePlan = Plan::create([
        'code' => 'event-plus',
        'name' => 'Event Plus',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    EventPurchase::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'plan_id' => $purchasePlan->id,
        'price_snapshot_cents' => 9900,
        'currency' => 'BRL',
        'features_snapshot_json' => [
            'play.enabled' => true,
            'media.max_photos' => 150,
        ],
        'status' => 'paid',
        'purchased_by_user_id' => $user->id,
        'purchased_at' => now(),
    ]);

    $event->refresh();

    expect($event->commercial_mode?->value)->toBe('single_purchase');
    expect($event->current_entitlements_json['modules']['play'] ?? null)->toBeTrue();
    expect($event->current_entitlements_json['limits']['max_photos'] ?? null)->toBe(150);
});

it('recalculates the event snapshot automatically when a grant is removed', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    $grant = EventAccessGrant::factory()
        ->forEvent($event)
        ->create([
            'granted_by_user_id' => $user->id,
            'source_type' => EventAccessGrantSourceType::Bonus->value,
            'merge_strategy' => EntitlementMergeStrategy::Expand->value,
            'priority' => EventAccessGrantSourceType::Bonus->defaultPriority(),
            'features_snapshot_json' => [
                'play.enabled' => true,
            ],
        ]);

    $event->refresh();

    expect($event->commercial_mode?->value)->toBe('bonus');
    expect($event->current_entitlements_json['modules']['play'] ?? null)->toBeTrue();

    $grant->delete();

    $event->refresh();

    expect($event->commercial_mode?->value)->toBe('none');
    expect($event->current_entitlements_json['commercial_mode'] ?? null)->toBe('none');
});
