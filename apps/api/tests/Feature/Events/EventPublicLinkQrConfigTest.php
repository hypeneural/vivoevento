<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Events\Models\EventPublicLinkQrConfig;
use Illuminate\Support\Facades\Schema;

function createEventWithPublicLinks(int $organizationId, array $attributes = []): Event
{
    $event = Event::factory()->active()->create(array_merge([
        'organization_id' => $organizationId,
    ], $attributes));

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);
    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);
    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'hub',
        'is_enabled' => true,
    ]);
    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'play',
        'is_enabled' => true,
    ]);

    return $event;
}

it('returns a default QR config derived from effective branding when nothing was saved', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = createEventWithPublicLinks($organization->id, [
        'title' => 'Evento QR',
        'primary_color' => '#112233',
        'secondary_color' => '#445566',
        'logo_path' => 'https://cdn.example.com/event-logo.png',
    ]);

    $response = $this->apiGet("/events/{$event->id}/qr-codes/upload");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.link_key', 'upload')
        ->assertJsonPath('data.config_source', 'default')
        ->assertJsonPath('data.has_saved_config', false)
        ->assertJsonPath('data.config.usage_preset', 'upload_rapido')
        ->assertJsonPath('data.config.skin_preset', 'premium')
        ->assertJsonPath('data.config.style.dots.color', '#112233')
        ->assertJsonPath('data.config.style.corners_dot.color', '#445566')
        ->assertJsonPath('data.config.logo.mode', 'event_logo')
        ->assertJsonPath('data.config.logo.asset_url', 'https://cdn.example.com/event-logo.png');

    expect((string) $response->json('data.link.qr_value'))->toContain($event->upload_slug);
});

it('persists a normalized QR config per event and link and resets back to default', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = createEventWithPublicLinks($organization->id);

    $saveResponse = $this->apiPut("/events/{$event->id}/qr-codes/upload", [
        'config' => [
            'usage_preset' => 'upload_rapido',
            'skin_preset' => 'minimalista',
            'render' => [
                'preview_type' => 'svg',
                'preview_size' => 320,
                'margin_modules' => 1,
                'background_mode' => 'transparent',
            ],
            'style' => [
                'background' => [
                    'transparent' => true,
                ],
            ],
            'logo' => [
                'mode' => 'custom',
                'asset_url' => 'https://cdn.example.com/custom-logo.png',
                'image_size' => 0.9,
            ],
            'advanced' => [
                'error_correction_level' => 'Q',
            ],
            'export_defaults' => [
                'extension' => 'jpeg',
                'size' => 2048,
            ],
        ],
    ]);

    $this->assertApiSuccess($saveResponse);

    $saveResponse->assertJsonPath('data.config_source', 'saved')
        ->assertJsonPath('data.has_saved_config', true)
        ->assertJsonPath('data.config.skin_preset', 'minimalista')
        ->assertJsonPath('data.config.render.margin_modules', 4)
        ->assertJsonPath('data.config.logo.image_size', 0.5)
        ->assertJsonPath('data.config.advanced.error_correction_level', 'H')
        ->assertJsonPath('data.config.export_defaults.extension', 'png');

    expect(EventPublicLinkQrConfig::query()->where('event_id', $event->id)->where('link_key', 'upload')->exists())->toBeTrue();

    $resetResponse = $this->apiPost("/events/{$event->id}/qr-codes/upload/reset");

    $this->assertApiSuccess($resetResponse);

    $resetResponse->assertJsonPath('data.config_source', 'default')
        ->assertJsonPath('data.has_saved_config', false)
        ->assertJsonPath('data.config.usage_preset', 'upload_rapido');

    expect(EventPublicLinkQrConfig::query()->where('event_id', $event->id)->where('link_key', 'upload')->exists())->toBeFalse();
});

it('keeps the saved visual config while qr_value follows the latest public identifier', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = createEventWithPublicLinks($organization->id, [
        'title' => 'Casamento QR',
        'slug' => 'casamento-antigo',
    ]);

    $saveResponse = $this->apiPut("/events/{$event->id}/qr-codes/gallery", [
        'config' => [
            'style' => [
                'dots' => [
                    'color' => '#123456',
                ],
            ],
        ],
    ]);

    $this->assertApiSuccess($saveResponse);

    $updateIdentifiersResponse = $this->apiPatch("/events/{$event->id}/public-links", [
        'slug' => 'casamento-novo',
    ]);

    $this->assertApiSuccess($updateIdentifiersResponse);

    $getResponse = $this->apiGet("/events/{$event->id}/qr-codes/gallery");

    $this->assertApiSuccess($getResponse);

    $getResponse->assertJsonPath('data.config.style.dots.color', '#123456')
        ->assertJsonPath('data.config_source', 'saved');

    expect((string) $getResponse->json('data.link.qr_value'))->toContain('/e/casamento-novo');
});

it('forbids QR config access outside the current organization', function () {
    [$user, $organization] = $this->actingAsOwner();

    $otherOrganization = $this->createOrganization();
    $event = createEventWithPublicLinks($otherOrganization->id);

    $response = $this->apiGet("/events/{$event->id}/qr-codes/gallery");

    $this->assertApiForbidden($response);
});

it('falls back to default QR state when the QR config table is not available yet', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = createEventWithPublicLinks($organization->id, [
        'title' => 'Evento sem migration do QR',
        'primary_color' => '#112233',
    ]);

    Schema::dropIfExists('event_public_link_qr_configs');

    $response = $this->apiGet("/events/{$event->id}/qr-codes/gallery");
    $listResponse = $this->apiGet("/events/{$event->id}/qr-codes");

    $this->assertApiSuccess($response);
    $this->assertApiSuccess($listResponse);

    $response->assertJsonPath('data.link_key', 'gallery')
        ->assertJsonPath('data.config_source', 'default')
        ->assertJsonPath('data.has_saved_config', false)
        ->assertJsonPath('data.config.style.dots.color', '#112233');

    expect($listResponse->json('data'))->toBeArray();
    expect(collect($listResponse->json('data'))->pluck('link_key'))->toContain('gallery');
});
