<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;

// ──────────────────────────────────────────────────────────
// Dashboard Stats Endpoint
// ──────────────────────────────────────────────────────────

it('returns dashboard stats with kpis charts and lists', function () {
    [$user, $org] = $this->actingAsOwner();

    // Seed some data
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'status'          => 'active',
        'event_type'      => 'wedding',
        'created_by'      => $user->id,
    ]);

    // Create media items
    EventMedia::factory()->count(5)->create([
        'event_id'          => $event->id,
        'moderation_status' => 'approved',
    ]);
    EventMedia::factory()->count(2)->create([
        'event_id'          => $event->id,
        'moderation_status' => 'pending',
    ]);

    $response = $this->apiGet('/dashboard/stats');

    $this->assertApiSuccess($response);

    $data = $response->json('data');

    // KPIs
    expect($data)->toHaveKey('kpis');
    expect($data['kpis'])->toHaveKeys([
        'active_events',
        'active_events_subscription_covered',
        'active_events_single_purchase',
        'active_events_trial',
        'active_events_bonus',
        'photos_today',
        'photos_approved_today',
        'moderation_rate',
        'games_played',
        'hub_accesses',
        'revenue_cents',
        'subscription_revenue_cents',
        'event_revenue_cents',
        'pending_moderation',
        'processing_errors',
        'active_partners',
    ]);
    expect($data['kpis']['active_events'])->toBe(1);
    expect($data['kpis']['photos_today'])->toBe(7);
    expect($data['kpis']['photos_approved_today'])->toBe(5);
    expect($data['kpis']['pending_moderation'])->toBe(2);

    // Changes
    expect($data)->toHaveKey('changes');
    expect($data['changes'])->toHaveKeys([
        'photos_today_change',
        'events_new_this_week',
        'games_played_today',
    ]);

    // Charts
    expect($data)->toHaveKey('charts');
    expect($data['charts'])->toHaveKeys([
        'uploads_per_hour',
        'events_by_type',
        'engagement_by_module',
    ]);
    expect($data['charts']['uploads_per_hour'])->toHaveCount(24);
    expect($data['charts']['events_by_type'])->toBeArray();

    // Recent events
    expect($data)->toHaveKey('recent_events');
    expect($data['recent_events'])->toHaveCount(1);
    expect($data['recent_events'][0]['title'])->toBe($event->title);
    expect($data['recent_events'][0]['photos_received'])->toBe(7);

    // Moderation queue
    expect($data)->toHaveKey('moderation_queue');
    expect($data['moderation_queue'])->toHaveCount(2);

    // Alerts
    expect($data)->toHaveKey('alerts');
    expect($data['alerts'])->toBeArray();
});

it('returns correct moderation rate calculation', function () {
    [$user, $org] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'status'          => 'active',
    ]);

    EventMedia::factory()->count(8)->create([
        'event_id'          => $event->id,
        'moderation_status' => 'approved',
    ]);
    EventMedia::factory()->count(2)->create([
        'event_id'          => $event->id,
        'moderation_status' => 'pending',
    ]);

    $response = $this->apiGet('/dashboard/stats');

    $this->assertApiSuccess($response);
    $kpis = $response->json('data.kpis');

    expect($kpis['photos_today'])->toBe(10);
    expect($kpis['photos_approved_today'])->toBe(8);
    expect($kpis['moderation_rate'])->toEqual(80.0);
});

it('scopes data to the authenticated users organization', function () {
    [$user, $org] = $this->actingAsOwner();

    // Events in user's org
    Event::factory()->count(2)->create([
        'organization_id' => $org->id,
        'status'          => 'active',
    ]);

    // Events in another org — should NOT appear
    $otherOrg = \App\Modules\Organizations\Models\Organization::factory()->create();
    Event::factory()->count(3)->create([
        'organization_id' => $otherOrg->id,
        'status'          => 'active',
    ]);

    $response = $this->apiGet('/dashboard/stats');

    $this->assertApiSuccess($response);
    expect($response->json('data.kpis.active_events'))->toBe(2);
    expect($response->json('data.recent_events'))->toHaveCount(2);
});

it('returns events by type chart data', function () {
    [$user, $org] = $this->actingAsOwner();

    Event::factory()->count(3)->create([
        'organization_id' => $org->id,
        'event_type'      => 'wedding',
    ]);
    Event::factory()->count(2)->create([
        'organization_id' => $org->id,
        'event_type'      => 'corporate',
    ]);

    $response = $this->apiGet('/dashboard/stats');

    $this->assertApiSuccess($response);

    $types = collect($response->json('data.charts.events_by_type'));
    expect($types->firstWhere('type', 'wedding')['count'])->toBe(3);
    expect($types->firstWhere('type', 'corporate')['count'])->toBe(2);
});

it('returns partner revenue split for admin dashboard ranking', function () {
    [$admin] = $this->actingAsSuperAdmin();

    $topOrg = $this->createOrganization([
        'trade_name' => 'Studio Norte',
        'status' => 'active',
    ]);

    \App\Modules\Events\Models\Event::factory()->create([
        'organization_id' => $topOrg->id,
        'status' => 'active',
        'commercial_mode' => 'subscription_covered',
    ]);

    \App\Modules\Events\Models\Event::factory()->create([
        'organization_id' => $topOrg->id,
        'status' => 'active',
        'commercial_mode' => 'single_purchase',
    ]);

    $subscriptionOrder = \App\Modules\Billing\Models\BillingOrder::create([
        'organization_id' => $topOrg->id,
        'buyer_user_id' => $admin->id,
        'mode' => 'subscription',
        'status' => 'paid',
        'currency' => 'BRL',
        'total_cents' => 9900,
        'confirmed_at' => now(),
    ]);

    $eventOrder = \App\Modules\Billing\Models\BillingOrder::create([
        'organization_id' => $topOrg->id,
        'buyer_user_id' => $admin->id,
        'mode' => 'event_package',
        'status' => 'paid',
        'currency' => 'BRL',
        'total_cents' => 19900,
        'confirmed_at' => now(),
    ]);

    \App\Modules\Billing\Models\Invoice::create([
        'organization_id' => $topOrg->id,
        'billing_order_id' => $subscriptionOrder->id,
        'status' => 'paid',
        'amount_cents' => 9900,
        'currency' => 'BRL',
        'issued_at' => now(),
        'due_at' => now(),
        'paid_at' => now(),
    ]);

    \App\Modules\Billing\Models\Invoice::create([
        'organization_id' => $topOrg->id,
        'billing_order_id' => $eventOrder->id,
        'status' => 'paid',
        'amount_cents' => 19900,
        'currency' => 'BRL',
        'issued_at' => now(),
        'due_at' => now(),
        'paid_at' => now(),
    ]);

    $response = $this->apiGet('/dashboard/stats');

    $this->assertApiSuccess($response);

    $partner = collect($response->json('data.top_partners'))->firstWhere('id', $topOrg->id);

    expect($partner)->not()->toBeNull();
    expect($partner['active_subscription_events'])->toBe(1);
    expect($partner['active_paid_events'])->toBe(1);
    expect($partner['subscription_revenue'])->toBe(99);
    expect($partner['event_revenue'])->toBe(199);
    expect($partner['revenue'])->toBe(298);
});

it('rejects dashboard access for unauthenticated user', function () {
    $response = $this->getJson('/api/v1/dashboard/stats');
    $response->assertStatus(401);
});
