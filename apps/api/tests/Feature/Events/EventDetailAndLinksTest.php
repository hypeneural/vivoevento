<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Models\EventWallSetting;

it('returns an enriched event detail payload with stats menu and public links', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Casamento API',
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'wall', 'is_enabled' => false]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'play', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);
    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'provider_key' => 'vllm',
        'mode' => 'enrich_only',
    ]);

    EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => 'pending',
        'publication_status' => 'draft',
    ]);
    EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'publication_status' => 'draft',
    ]);
    EventMedia::factory()->published()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.module_flags.live', true)
        ->assertJsonPath('data.module_flags.wall', false)
        ->assertJsonPath('data.module_flags.play', true)
        ->assertJsonPath('data.stats.media_total', 3)
        ->assertJsonPath('data.stats.media_pending', 1)
        ->assertJsonPath('data.stats.media_approved', 2)
        ->assertJsonPath('data.stats.media_published', 1)
        ->assertJsonPath('data.public_links.gallery.enabled', true)
        ->assertJsonPath('data.public_links.upload.enabled', true)
        ->assertJsonPath('data.public_links.wall.enabled', false)
        ->assertJsonPath('data.public_identifiers.slug.value', $event->slug)
        ->assertJsonPath('data.media_intelligence.provider_key', 'vllm')
        ->assertJsonPath('data.media_intelligence.enabled', true);

    expect($response->json('data.menu.0.key'))->toBe('overview')
        ->and($response->json('data.menu.4.visible'))->toBeFalse();
});

it('updates and regenerates public identifiers for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento com links',
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'wall', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'play', 'is_enabled' => false]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);

    $wallSettings = EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $updateResponse = $this->apiPatch("/events/{$event->id}/public-links", [
        'slug' => 'casamento-personalizado',
        'upload_slug' => 'envio-casamento',
    ]);

    $this->assertApiSuccess($updateResponse);
    $updateResponse->assertJsonPath('data.identifiers.slug.value', 'casamento-personalizado')
        ->assertJsonPath('data.identifiers.upload_slug.value', 'envio-casamento');

    $event->refresh();
    expect($event->slug)->toBe('casamento-personalizado')
        ->and($event->upload_slug)->toBe('envio-casamento')
        ->and($event->public_url)->toContain('/e/casamento-personalizado')
        ->and($event->upload_url)->toContain('/upload/envio-casamento');

    $regenerateResponse = $this->apiPost("/events/{$event->id}/public-links/regenerate", [
        'fields' => ['slug', 'upload_slug', 'wall_code'],
    ]);

    $this->assertApiSuccess($regenerateResponse);

    $event->refresh();
    $wallSettings->refresh();

    expect($event->slug)->not->toBe('casamento-personalizado')
        ->and($event->upload_slug)->not->toBe('envio-casamento')
        ->and(strlen((string) $wallSettings->wall_code))->toBe(8);
});

it('forbids event detail access outside the user organization', function () {
    [$user, $organization] = $this->actingAsOwner();
    $otherOrganization = $this->createOrganization();

    $event = Event::factory()->active()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}");

    $this->assertApiForbidden($response);
});
