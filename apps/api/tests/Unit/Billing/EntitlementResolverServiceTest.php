<?php

use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Billing\Models\EventPurchase;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\EntitlementResolverService;
use App\Modules\Events\Models\Event;
use App\Modules\Plans\Models\Plan;

it('expands the subscription baseline with an active bonus grant', function () {
    $organization = \App\Modules\Organizations\Models\Organization::factory()->create();

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

    EventAccessGrant::factory()
        ->forEvent($event)
        ->create([
            'source_type' => EventAccessGrantSourceType::Bonus->value,
            'merge_strategy' => EntitlementMergeStrategy::Expand->value,
            'priority' => EventAccessGrantSourceType::Bonus->defaultPriority(),
            'features_snapshot_json' => [
                'play.enabled' => true,
                'white_label.enabled' => true,
            ],
            'limits_snapshot_json' => [
                'media.retention_days' => 120,
                'media.max_photos' => 500,
            ],
        ]);

    $resolved = app(EntitlementResolverService::class)->resolve($event);

    expect($resolved['commercial_mode'])->toBe('bonus');
    expect($resolved['resolved_entitlements']['modules']['wall'])->toBeTrue();
    expect($resolved['resolved_entitlements']['modules']['play'])->toBeTrue();
    expect($resolved['resolved_entitlements']['branding']['white_label'])->toBeTrue();
    expect($resolved['resolved_entitlements']['limits']['retention_days'])->toBe(120);
    expect($resolved['resolved_entitlements']['limits']['max_photos'])->toBe(500);
});

it('restricts the baseline when a trial grant is active', function () {
    $organization = \App\Modules\Organizations\Models\Organization::factory()->create();

    $plan = Plan::create([
        'code' => 'starter',
        'name' => 'Starter',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'true'],
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

    EventAccessGrant::factory()
        ->forEvent($event)
        ->create([
            'source_type' => EventAccessGrantSourceType::Trial->value,
            'merge_strategy' => EntitlementMergeStrategy::Restrict->value,
            'priority' => EventAccessGrantSourceType::Trial->defaultPriority(),
            'features_snapshot_json' => [
                'wall.enabled' => false,
                'play.enabled' => false,
                'gallery.watermark' => true,
            ],
            'limits_snapshot_json' => [
                'media.retention_days' => 7,
            ],
        ]);

    $resolved = app(EntitlementResolverService::class)->resolve($event);

    expect($resolved['commercial_mode'])->toBe('trial');
    expect($resolved['resolved_entitlements']['modules']['wall'])->toBeFalse();
    expect($resolved['resolved_entitlements']['modules']['play'])->toBeFalse();
    expect($resolved['resolved_entitlements']['branding']['watermark'])->toBeFalse();
    expect($resolved['resolved_entitlements']['limits']['retention_days'])->toBe(7);
});

it('uses package-backed purchases when the legacy plan reference is absent', function () {
    $organization = \App\Modules\Organizations\Models\Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    $package = EventPackage::factory()->create([
        'code' => 'interactive-event',
        'name' => 'Interactive Event',
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

    EventPurchase::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'plan_id' => null,
        'package_id' => $package->id,
        'price_snapshot_cents' => 19900,
        'currency' => 'BRL',
        'features_snapshot_json' => null,
        'status' => 'paid',
        'purchased_at' => now(),
    ]);

    $resolved = app(EntitlementResolverService::class)->resolve($event);

    expect($resolved['commercial_mode'])->toBe('single_purchase');
    expect($resolved['purchase_summary']['catalog_type'])->toBe('event_package');
    expect($resolved['purchase_summary']['package_code'])->toBe('interactive-event');
    expect($resolved['purchase_summary']['plan_id'])->toBeNull();
    expect($resolved['resolved_entitlements']['modules']['wall'])->toBeTrue();
    expect($resolved['resolved_entitlements']['modules']['play'])->toBeTrue();
    expect($resolved['resolved_entitlements']['limits']['max_photos'])->toBe(300);
});

it('keeps the subscription baseline active while a canceled subscription is still within the paid period', function () {
    $organization = \App\Modules\Organizations\Models\Organization::factory()->create();

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
        'status' => 'canceled',
        'billing_cycle' => 'monthly',
        'starts_at' => now()->subDays(10),
        'canceled_at' => now(),
        'ends_at' => now()->addDays(20),
        'renews_at' => now()->addDays(20),
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    $resolved = app(EntitlementResolverService::class)->resolve($event);

    expect($resolved['commercial_mode'])->toBe('subscription_covered');
    expect($resolved['subscription_summary']['status'])->toBe('canceled');
    expect($resolved['subscription_summary']['active'])->toBeTrue();
    expect($resolved['resolved_entitlements']['modules']['wall'])->toBeTrue();
    expect($resolved['resolved_entitlements']['limits']['retention_days'])->toBe(90);
});
