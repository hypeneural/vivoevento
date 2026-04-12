<?php

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;

it('creates a shareable preview token for the current draft and serves the public preview payload from that revision', function () {
    [, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Preview da galeria',
        'primary_color' => '#101828',
        'secondary_color' => '#d946ef',
    ]);

    $experience = app(GalleryBuilderSchemaRegistry::class)->baseExperience();

    $this->apiPatch("/events/{$event->id}/gallery/settings", [
        'event_type_family' => 'wedding',
        'style_skin' => 'premium',
        'behavior_profile' => 'story',
        'theme_key' => 'black-tie',
        'layout_key' => 'justified-story',
        'theme_tokens' => $experience['theme_tokens'],
        'page_schema' => array_replace_recursive($experience['page_schema'], [
            'blocks' => [
                'hero' => [
                    'variant' => 'wedding',
                    'show_face_search_cta' => false,
                ],
            ],
        ]),
        'media_behavior' => array_replace_recursive($experience['media_behavior'], [
            'grid' => [
                'layout' => 'justified',
            ],
        ]),
    ])->assertOk();

    $link = $this->apiPost("/events/{$event->id}/gallery/preview-link");

    $this->assertApiSuccess($link);
    $token = $link->json('data.token');

    expect($token)->not->toBeEmpty();
    $link->assertJsonPath('data.revision.kind', 'autosave')
        ->assertJsonPath('data.preview_url', fn ($value) => str_contains((string) $value, "/api/v1/public/gallery-previews/{$token}"))
        ->assertJsonPath('data.expires_at', fn ($value) => filled($value));

    $preview = $this->getJson("/api/v1/public/gallery-previews/{$token}");

    $preview->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('event.id', $event->id)
        ->assertJsonPath('event.title', 'Preview da galeria')
        ->assertJsonPath('experience.theme_key', 'black-tie')
        ->assertJsonPath('experience.layout_key', 'justified-story')
        ->assertJsonPath('experience.model_matrix.style_skin', 'premium')
        ->assertJsonPath('experience.media_behavior.grid.layout', 'justified')
        ->assertJsonPath('experience.page_schema.blocks.hero.show_face_search_cta', false);
});
