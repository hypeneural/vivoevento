<?php

use App\Modules\Billing\Enums\EventPackageAudience;
use App\Modules\Billing\Enums\EventPackageBillingMode;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Events\Models\Event;
use App\Modules\Plans\Models\Plan;

it('returns the recurring billing catalog as raw active plans with billing cycles and raw features', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = Plan::create([
        'code' => 'professional',
        'name' => 'Professional',
        'audience' => 'b2b',
        'status' => 'active',
        'description' => 'Plano recorrente para parceiros.',
    ]);

    $plan->prices()->createMany([
        [
            'billing_cycle' => 'monthly',
            'currency' => 'BRL',
            'amount_cents' => 9900,
            'is_default' => true,
        ],
        [
            'billing_cycle' => 'yearly',
            'currency' => 'BRL',
            'amount_cents' => 99900,
            'is_default' => false,
        ],
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'events.max_active', 'feature_value' => '10'],
        ['feature_key' => 'media.retention_days', 'feature_value' => '90'],
    ]);

    $response = $this->apiGet('/plans');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.code'))->toBe('professional');
    expect(collect($response->json('data.0.prices'))->pluck('billing_cycle')->all())
        ->toContain('monthly', 'yearly');
    expect(collect($response->json('data.0.features'))->pluck('feature_key')->all())
        ->toContain('wall.enabled', 'events.max_active', 'media.retention_days');
    expect(data_get($response->json(), 'data.0.limits'))->toBeNull();
    expect(data_get($response->json(), 'data.0.modules'))->toBeNull();
});

it('builds event package technical projection and checkout marketing from the same raw feature bag', function () {
    [$user, $organization] = $this->actingAsOwner();

    $package = EventPackage::factory()->create([
        'code' => 'interactive-event',
        'name' => 'Interactive Event',
        'description' => 'Pacote para um evento avulso mais interativo.',
        'target_audience' => EventPackageAudience::Both->value,
        'is_active' => true,
    ]);

    $package->prices()->create([
        'billing_mode' => EventPackageBillingMode::OneTime->value,
        'currency' => 'BRL',
        'amount_cents' => 19900,
        'is_active' => true,
        'is_default' => true,
    ]);

    $package->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'false'],
        ['feature_key' => 'media.max_photos', 'feature_value' => '400'],
        ['feature_key' => 'media.retention_days', 'feature_value' => '90'],
        ['feature_key' => 'checkout.badge', 'feature_value' => 'Mais escolhido'],
        ['feature_key' => 'checkout.recommended', 'feature_value' => 'true'],
        ['feature_key' => 'checkout.benefit_1', 'feature_value' => 'Telao ao vivo para os convidados'],
    ]);

    $response = $this->apiGet("/event-packages/{$package->id}");

    $this->assertApiSuccess($response);
    expect(collect($response->json('data.features'))->pluck('feature_key')->all())
        ->toContain(
            'wall.enabled',
            'play.enabled',
            'media.max_photos',
            'media.retention_days',
            'checkout.badge',
            'checkout.recommended',
            'checkout.benefit_1',
        );
    expect($response->json('data.modules.wall'))->toBeTrue();
    expect($response->json('data.modules.play'))->toBeFalse();
    expect($response->json('data.limits.max_photos'))->toBe(400);
    expect($response->json('data.limits.retention_days'))->toBe(90);
    expect($response->json('data.checkout_marketing.badge'))->toBe('Mais escolhido');
    expect($response->json('data.checkout_marketing.recommended'))->toBeTrue();
    expect($response->json('data.checkout_marketing.benefits'))->toBe(['Telao ao vivo para os convidados']);
});

it('creates the public trial through an event grant without creating a recurring subscription', function () {
    $this->seedPermissions();

    $response = $this->apiPost('/public/trial-events', [
        'responsible_name' => 'Fernanda Souza',
        'whatsapp' => '(48) 99999-1111',
        'email' => 'fernanda@example.com',
        'organization_name' => 'Fernanda Eventos',
        'device_name' => 'trial-web',
        'event' => [
            'title' => 'Casamento Fernanda & Lucas',
            'event_type' => 'wedding',
            'event_date' => '2026-10-12',
            'city' => 'Tijucas',
            'description' => 'Evento teste para validar a experiencia.',
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $organizationId = $response->json('data.organization.id');
    $eventId = $response->json('data.event.id');

    $this->assertDatabaseHas('event_access_grants', [
        'organization_id' => $organizationId,
        'event_id' => $eventId,
        'source_type' => 'trial',
        'status' => 'active',
    ]);
    $this->assertDatabaseMissing('subscriptions', [
        'organization_id' => $organizationId,
    ]);

    expect($response->json('data.commercial_status.commercial_mode'))->toBe('trial');
    expect($response->json('data.trial.modules.live'))->toBeTrue();
    expect($response->json('data.trial.modules.hub'))->toBeTrue();
    expect($response->json('data.trial.modules.wall'))->toBeFalse();
    expect($response->json('data.trial.modules.play'))->toBeFalse();
    expect($response->json('data.trial.limits.max_active_events'))->toBe(1);
    expect($response->json('data.trial.limits.max_photos'))->toBe(20);
    expect($response->json('data.trial.limits.retention_days'))->toBe(7);
});

it('resolves subscription covered events directly from the organization subscription baseline without event grants', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = Plan::create([
        'code' => 'starter',
        'name' => 'Starter',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'media.retention_days', 'feature_value' => '30'],
    ]);

    Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now()->subDay(),
        'renews_at' => now()->addMonth(),
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    $response = $this->apiGet("/events/{$event->id}/commercial-status");

    $this->assertApiSuccess($response);
    $this->assertDatabaseMissing('event_access_grants', [
        'event_id' => $event->id,
    ]);
    expect($response->json('data.commercial_mode'))->toBe('subscription_covered');
    expect($response->json('data.subscription_summary.plan_key'))->toBe('starter');
    expect($response->json('data.resolved_entitlements.modules.wall'))->toBeTrue();
    expect($response->json('data.resolved_entitlements.limits.retention_days'))->toBe(30);
});
