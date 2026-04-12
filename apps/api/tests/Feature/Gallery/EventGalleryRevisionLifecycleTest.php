<?php

use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\EventGalleryRevision;
use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;

it('creates autosave and published revisions and restores a previous gallery revision', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Galeria Revision Lifecycle',
        'primary_color' => '#111827',
        'secondary_color' => '#f97316',
    ]);

    $experience = app(GalleryBuilderSchemaRegistry::class)->baseExperience();

    $this->apiPatch("/events/{$event->id}/gallery/settings", [
        'event_type_family' => 'wedding',
        'style_skin' => 'premium',
        'behavior_profile' => 'light',
        'theme_key' => 'black-tie',
        'layout_key' => 'editorial-masonry',
        'theme_tokens' => $experience['theme_tokens'],
        'page_schema' => $experience['page_schema'],
        'media_behavior' => $experience['media_behavior'],
    ])->assertOk();

    $autosave = $this->apiPost("/events/{$event->id}/gallery/autosave");

    $this->assertApiSuccess($autosave);
    $autosave->assertJsonPath('data.revision.kind', 'autosave')
        ->assertJsonPath('data.revision.version_number', 1)
        ->assertJsonPath('data.settings.draft_version', 1)
        ->assertJsonPath('data.settings.published_version', null);

    $firstRevisionId = $autosave->json('data.revision.id');

    $this->apiPatch("/events/{$event->id}/gallery/settings", [
        'event_type_family' => 'corporate',
        'style_skin' => 'clean',
        'behavior_profile' => 'sponsors',
        'theme_key' => 'corporate-clean',
        'layout_key' => 'timeless-rows',
        'theme_tokens' => array_replace_recursive($experience['theme_tokens'], [
            'palette' => [
                'page_background' => '#f8fafc',
                'surface_background' => '#ffffff',
                'surface_border' => '#cbd5e1',
                'text_primary' => '#0f172a',
                'text_secondary' => '#334155',
                'accent' => '#0f766e',
                'button_fill' => '#0f766e',
                'button_text' => '#ffffff',
            ],
        ]),
        'page_schema' => array_replace_recursive($experience['page_schema'], [
            'blocks' => [
                'hero' => [
                    'variant' => 'corporate',
                ],
            ],
        ]),
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

    $publish = $this->apiPost("/events/{$event->id}/gallery/publish");

    $this->assertApiSuccess($publish);
    $publish->assertJsonPath('data.revision.kind', 'published')
        ->assertJsonPath('data.revision.version_number', 2)
        ->assertJsonPath('data.settings.theme_key', 'corporate-clean')
        ->assertJsonPath('data.settings.layout_key', 'timeless-rows')
        ->assertJsonPath('data.settings.draft_version', 2)
        ->assertJsonPath('data.settings.published_version', 2);

    $publishAnalytics = AnalyticsEvent::query()
        ->where('event_id', $event->id)
        ->where('event_name', 'gallery.builder_published')
        ->first();

    expect($publishAnalytics)->not->toBeNull()
        ->and($publishAnalytics?->channel)->toBe('gallery_builder')
        ->and($publishAnalytics?->metadata_json['version_number'] ?? null)->toBe(2);

    $revisions = $this->apiGet("/events/{$event->id}/gallery/revisions");

    $this->assertApiSuccess($revisions);
    expect($revisions->json('data'))->toHaveCount(2);
    $revisions->assertJsonPath('data.0.kind', 'published')
        ->assertJsonPath('data.0.version_number', 2)
        ->assertJsonPath('data.1.kind', 'autosave')
        ->assertJsonPath('data.1.version_number', 1);

    $restore = $this->apiPost("/events/{$event->id}/gallery/revisions/{$firstRevisionId}/restore");

    $this->assertApiSuccess($restore);
    $restore->assertJsonPath('data.revision.kind', 'restored')
        ->assertJsonPath('data.revision.version_number', 3)
        ->assertJsonPath('data.settings.theme_key', 'black-tie')
        ->assertJsonPath('data.settings.event_type_family', 'wedding')
        ->assertJsonPath('data.settings.draft_version', 3)
        ->assertJsonPath('data.settings.published_version', 2)
        ->assertJsonPath('data.settings.current_preset_origin.origin_type', 'restore')
        ->assertJsonPath('data.settings.current_preset_origin.key', 'revision:'.$firstRevisionId)
        ->assertJsonPath('data.settings.updated_by', $user->id);

    $restoreAnalytics = AnalyticsEvent::query()
        ->where('event_id', $event->id)
        ->where('event_name', 'gallery.builder_restored')
        ->first();

    expect($restoreAnalytics)->not->toBeNull()
        ->and($restoreAnalytics?->channel)->toBe('gallery_builder')
        ->and($restoreAnalytics?->metadata_json['restored_from_revision_id'] ?? null)->toBe($firstRevisionId)
        ->and($restoreAnalytics?->metadata_json['version_number'] ?? null)->toBe(3);

    $this->assertDatabaseHas('event_gallery_revisions', [
        'event_id' => $event->id,
        'version_number' => 3,
        'kind' => 'restored',
        'created_by' => $user->id,
        'theme_key' => 'black-tie',
    ]);

    expect(EventGalleryRevision::query()->where('event_id', $event->id)->count())->toBe(3);
});
