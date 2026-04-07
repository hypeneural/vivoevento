<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaProcessing\Models\EventMedia;

it('uses the authenticated user current organization when no organization filter is provided', function () {
    [$user, $organization] = $this->actingAsOwner();
    $otherOrganization = $this->createOrganization();

    Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento da organizacao atual',
    ]);

    Event::factory()->create([
        'organization_id' => $otherOrganization->id,
        'title' => 'Evento de outra organizacao',
    ]);

    $response = $this->apiGet('/events');

    $this->assertApiSuccess($response);
    expect(collect($response->json('data'))->pluck('title')->all())
        ->toContain('Evento da organizacao atual')
        ->not->toContain('Evento de outra organizacao');
});

it('filters events by status type module and period', function () {
    [$user, $organization] = $this->actingAsOwner();

    $matchingEvent = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Casamento filtrado',
        'event_type' => 'wedding',
        'status' => 'active',
        'starts_at' => now()->addDays(14),
        'ends_at' => now()->addDays(14)->addHours(6),
    ]);

    EventModule::query()->create([
        'event_id' => $matchingEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    EventMedia::factory()->count(3)->create([
        'event_id' => $matchingEvent->id,
    ]);

    $filteredOut = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento fora do filtro',
        'event_type' => 'corporate',
        'status' => 'draft',
        'starts_at' => now()->addDays(45),
        'ends_at' => now()->addDays(45)->addHours(4),
    ]);

    EventModule::query()->create([
        'event_id' => $filteredOut->id,
        'module_key' => 'hub',
        'is_enabled' => true,
    ]);

    $response = $this->apiGet('/events?' . http_build_query([
        'status' => 'active',
        'event_type' => 'wedding',
        'module' => 'wall',
        'date_from' => now()->addDays(10)->toDateString(),
        'date_to' => now()->addDays(20)->toDateString(),
    ]));

    $this->assertApiSuccess($response);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.title'))->toBe('Casamento filtrado');
    expect($response->json('data.0.media_count'))->toBe(3);
    expect($response->json('data.0.enabled_modules'))->toContain('wall');
    expect($response->json('data.0'))->not->toHaveKeys([
        'description',
        'logo_path',
        'logo_url',
        'upload_api_url',
        'retention_days',
        'module_count',
        'current_entitlements',
        'intake_defaults',
        'intake_channels',
        'intake_blacklist',
    ]);
});

it('publishes and archives an event through status actions', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->draft()->create([
        'organization_id' => $organization->id,
    ]);

    $publishResponse = $this->apiPost("/events/{$event->id}/publish");
    $this->assertApiSuccess($publishResponse);
    expect($publishResponse->json('data.status'))->toBe('active');

    $archiveResponse = $this->apiPost("/events/{$event->id}/archive");
    $this->assertApiSuccess($archiveResponse);
    expect($archiveResponse->json('data.status'))->toBe('archived');
});

it('searches events by organization name and exposes the commercial mode in the list payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $organization->forceFill([
        'trade_name' => 'Studio Horizonte',
    ])->save();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento sem o nome da organizacao no titulo',
        'commercial_mode' => 'trial',
    ]);

    $response = $this->apiGet('/events?' . http_build_query([
        'search' => 'Studio Horizonte',
    ]));

    $this->assertApiSuccess($response);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($event->id);
    expect($response->json('data.0.commercial_mode'))->toBe('trial');
});
