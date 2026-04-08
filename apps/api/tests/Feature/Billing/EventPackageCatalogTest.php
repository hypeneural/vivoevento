<?php

use App\Modules\Billing\Enums\EventPackageAudience;
use App\Modules\Billing\Enums\EventPackageBillingMode;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Billing\Models\EventPackageFeature;
use App\Modules\Billing\Models\EventPackagePrice;

it('returns only active public event packages and sorts them by sort order', function () {
    $premium = EventPackage::factory()->create([
        'code' => 'premium-event',
        'name' => 'Premium',
        'target_audience' => EventPackageAudience::Both->value,
        'sort_order' => 30,
        'is_active' => true,
    ]);

    $essential = EventPackage::factory()->create([
        'code' => 'essential-event',
        'name' => 'Essencial',
        'target_audience' => EventPackageAudience::DirectCustomer->value,
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $partnerOnly = EventPackage::factory()->create([
        'code' => 'partner-exclusive',
        'name' => 'Partner Exclusive',
        'target_audience' => EventPackageAudience::Partner->value,
        'sort_order' => 5,
        'is_active' => true,
    ]);

    EventPackage::factory()->create([
        'code' => 'hidden-event',
        'name' => 'Oculto',
        'target_audience' => EventPackageAudience::Both->value,
        'sort_order' => 20,
        'is_active' => false,
    ]);

    foreach ([$premium, $essential, $partnerOnly] as $package) {
        EventPackagePrice::factory()->create([
            'event_package_id' => $package->id,
            'billing_mode' => EventPackageBillingMode::OneTime->value,
            'amount_cents' => $package->code === 'premium-event' ? 29900 : 9900,
            'is_default' => true,
        ]);
    }

    $response = $this->apiGet('/public/event-packages');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(2);
    expect(array_column($response->json('data'), 'code'))->toBe(['essential-event', 'premium-event']);
});

it('filters public event packages by target audience including shared packages', function () {
    $partnerPackage = EventPackage::factory()->create([
        'code' => 'partner-upgrade',
        'target_audience' => EventPackageAudience::Partner->value,
        'sort_order' => 20,
        'is_active' => true,
    ]);

    $sharedPackage = EventPackage::factory()->create([
        'code' => 'shared-upgrade',
        'target_audience' => EventPackageAudience::Both->value,
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $directPackage = EventPackage::factory()->create([
        'code' => 'direct-upgrade',
        'target_audience' => EventPackageAudience::DirectCustomer->value,
        'sort_order' => 30,
        'is_active' => true,
    ]);

    foreach ([$partnerPackage, $sharedPackage, $directPackage] as $package) {
        EventPackagePrice::factory()->create([
            'event_package_id' => $package->id,
            'is_default' => true,
        ]);
    }

    $response = $this->apiGet('/public/event-packages?target_audience=partner');

    $this->assertApiSuccess($response);
    expect(array_column($response->json('data'), 'code'))->toBe(['shared-upgrade', 'partner-upgrade']);
});

it('returns the authenticated event package catalog including inactive packages', function () {
    [$user, $organization] = $this->actingAsOwner();

    $activePackage = EventPackage::factory()->create([
        'code' => 'interactive-event',
        'is_active' => true,
    ]);

    $inactivePackage = EventPackage::factory()->create([
        'code' => 'legacy-event',
        'is_active' => false,
    ]);

    foreach ([$activePackage, $inactivePackage] as $package) {
        EventPackagePrice::factory()->create([
            'event_package_id' => $package->id,
            'is_default' => true,
        ]);
    }

    $response = $this->apiGet('/event-packages');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->pluck('code')->all())->toContain('legacy-event');
});

it('returns the event package detail with prices and feature map', function () {
    [$user, $organization] = $this->actingAsOwner();

    $package = EventPackage::factory()->create([
        'code' => 'premium-event',
        'name' => 'Premium Event',
        'target_audience' => EventPackageAudience::Both->value,
        'is_active' => true,
    ]);

    EventPackagePrice::factory()->create([
        'event_package_id' => $package->id,
        'billing_mode' => EventPackageBillingMode::OneTime->value,
        'amount_cents' => 29900,
        'is_default' => true,
    ]);

    EventPackageFeature::factory()->create([
        'event_package_id' => $package->id,
        'feature_key' => 'play.enabled',
        'feature_value' => 'true',
    ]);

    EventPackageFeature::factory()->create([
        'event_package_id' => $package->id,
        'feature_key' => 'media.max_photos',
        'feature_value' => '800',
    ]);

    $response = $this->apiGet("/event-packages/{$package->id}");

    $this->assertApiSuccess($response);
    expect($response->json('data.code'))->toBe('premium-event');
    expect($response->json('data.default_price.amount_cents'))->toBe(29900);
    expect($response->json('data.feature_map.play.enabled'))->toBe('true');
    expect($response->json('data.modules.play'))->toBeTrue();
    expect($response->json('data.limits.max_photos'))->toBe(800);
});
