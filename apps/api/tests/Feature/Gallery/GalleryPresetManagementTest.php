<?php

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\GalleryPreset;
use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;

it('lists gallery presets from the current organization only', function () {
    [, $organization] = $this->actingAsOwner();

    $otherOrganization = \App\Modules\Organizations\Models\Organization::factory()->create();

    GalleryPreset::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Casamento Romantico',
    ]);

    GalleryPreset::factory()->create([
        'organization_id' => $otherOrganization->id,
        'name' => 'Preset Externo',
    ]);

    $response = $this->apiGet('/gallery/presets');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.name', 'Casamento Romantico');
});

it('stores a gallery preset using the current event gallery settings as the source payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento para preset',
    ]);

    $experience = app(GalleryBuilderSchemaRegistry::class)->baseExperience();

    $this->apiPatch("/events/{$event->id}/gallery/settings", [
        'event_type_family' => 'corporate',
        'style_skin' => 'clean',
        'behavior_profile' => 'sponsors',
        'theme_key' => 'corporate-clean',
        'layout_key' => 'timeless-rows',
        'theme_tokens' => array_replace_recursive($experience['theme_tokens'], [
            'palette' => [
                'accent' => '#0f766e',
                'button_fill' => '#0f766e',
            ],
        ]),
        'page_schema' => $experience['page_schema'],
        'media_behavior' => array_replace_recursive($experience['media_behavior'], [
            'grid' => [
                'layout' => 'rows',
            ],
            'interstitials' => [
                'enabled' => true,
                'policy' => 'sponsors',
            ],
        ]),
    ])->assertOk();

    $response = $this->apiPost('/gallery/presets', [
        'event_id' => $event->id,
        'name' => 'Corporate Clean Sponsors',
        'description' => 'Preset reutilizavel para patrocinadores e galeria institucional.',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Corporate Clean Sponsors')
        ->assertJsonPath('data.theme_key', 'corporate-clean')
        ->assertJsonPath('data.layout_key', 'timeless-rows')
        ->assertJsonPath('data.event_type_family', 'corporate')
        ->assertJsonPath('data.style_skin', 'clean')
        ->assertJsonPath('data.behavior_profile', 'sponsors')
        ->assertJsonPath('data.source_event.title', 'Evento para preset')
        ->assertJsonPath('data.payload.media_behavior.interstitials.policy', 'sponsors');

    $this->assertDatabaseHas('gallery_presets', [
        'organization_id' => $organization->id,
        'source_event_id' => $event->id,
        'created_by' => $user->id,
        'name' => 'Corporate Clean Sponsors',
        'theme_key' => 'corporate-clean',
        'layout_key' => 'timeless-rows',
        'derived_preset_key' => 'corporate.clean.sponsors',
    ]);
});
