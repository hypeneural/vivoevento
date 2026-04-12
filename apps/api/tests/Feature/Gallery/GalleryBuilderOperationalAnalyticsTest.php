<?php

use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\EventGalleryRevision;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Gallery\Models\GalleryBuilderPromptRun;
use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;

it('tracks preset telemetry and persists the current preset origin', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Gallery Builder Telemetry',
    ]);

    $response = $this->apiPost("/events/{$event->id}/gallery/telemetry", [
        'event' => 'preset_applied',
        'preset' => [
            'origin_type' => 'preset',
            'key' => 'casamento-premium',
            'label' => 'Casamento premium',
        ],
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.current_preset_origin.origin_type', 'preset')
        ->assertJsonPath('data.current_preset_origin.key', 'casamento-premium')
        ->assertJsonPath('data.current_preset_origin.label', 'Casamento premium')
        ->assertJsonPath('data.operational_feedback.current_preset_origin.label', 'Casamento premium');

    $settings = EventGallerySetting::query()->firstWhere('event_id', $event->id);

    expect($settings)->not->toBeNull()
        ->and($settings?->current_preset_origin_json['key'] ?? null)->toBe('casamento-premium')
        ->and($settings?->current_preset_origin_json['label'] ?? null)->toBe('Casamento premium')
        ->and($settings?->updated_by)->toBe($user->id);

    $analyticsEvent = AnalyticsEvent::query()
        ->where('event_id', $event->id)
        ->where('event_name', 'gallery.builder_preset_applied')
        ->first();

    expect($analyticsEvent)->not->toBeNull()
        ->and($analyticsEvent?->channel)->toBe('gallery_builder')
        ->and($analyticsEvent?->metadata_json['origin_key'] ?? null)->toBe('casamento-premium')
        ->and($analyticsEvent?->metadata_json['origin_label'] ?? null)->toBe('Casamento premium');
});

it('tracks ai application telemetry and stores the selected variation on the prompt run', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Gallery Builder AI Telemetry',
    ]);

    $run = GalleryBuilderPromptRun::factory()->create([
        'event_id' => $event->id,
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'selected_variation_id' => null,
    ]);

    $response = $this->apiPost("/events/{$event->id}/gallery/telemetry", [
        'event' => 'ai_applied',
        'run_id' => $run->id,
        'variation_id' => 'modern-clean',
        'apply_scope' => 'theme_tokens',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.operational_feedback.last_ai_application.run_id', $run->id)
        ->assertJsonPath('data.operational_feedback.last_ai_application.variation_id', 'modern-clean')
        ->assertJsonPath('data.operational_feedback.last_ai_application.apply_scope', 'theme_tokens');

    $run->refresh();

    expect($run->selected_variation_id)->toBe('modern-clean')
        ->and(data_get($run->response_payload_json, 'selected_variation.id'))->toBe('modern-clean')
        ->and(data_get($run->response_payload_json, 'selected_variation.apply_scope'))->toBe('theme_tokens');

    $analyticsEvent = AnalyticsEvent::query()
        ->where('event_id', $event->id)
        ->where('event_name', 'gallery.builder_ai_applied')
        ->first();

    expect($analyticsEvent)->not->toBeNull()
        ->and($analyticsEvent?->channel)->toBe('gallery_builder')
        ->and($analyticsEvent?->metadata_json['run_id'] ?? null)->toBe($run->id)
        ->and($analyticsEvent?->metadata_json['variation_id'] ?? null)->toBe('modern-clean');
});

it('returns optimized renderer trigger, feedback and stores vitals samples for the builder boot', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Gallery Builder Boot',
    ]);

    $experience = app(GalleryBuilderSchemaRegistry::class)->baseExperience();

    $publishedRevision = EventGalleryRevision::factory()->create([
        'event_id' => $event->id,
        'version_number' => 4,
        'kind' => 'published',
        'created_by' => $user->id,
        'theme_tokens_json' => $experience['theme_tokens'],
        'page_schema_json' => $experience['page_schema'],
        'media_behavior_json' => $experience['media_behavior'],
    ]);

    EventGalleryRevision::factory()->create([
        'event_id' => $event->id,
        'version_number' => 5,
        'kind' => 'restored',
        'created_by' => $user->id,
        'theme_tokens_json' => $experience['theme_tokens'],
        'page_schema_json' => $experience['page_schema'],
        'media_behavior_json' => $experience['media_behavior'],
        'change_summary_json' => [
            'reason' => 'Restaurado da versao 2',
            'restored_from_revision_id' => 12,
            'restored_from_version_number' => 2,
        ],
    ]);

    EventGallerySetting::factory()->create([
        'event_id' => $event->id,
        'current_published_revision_id' => $publishedRevision->id,
        'published_version' => 4,
        'current_preset_origin_json' => [
            'origin_type' => 'preset',
            'key' => 'wedding-premium',
            'label' => 'Wedding premium',
            'applied_at' => now()->subMinutes(10)->toIso8601String(),
            'applied_by' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
        ],
    ]);

    GalleryBuilderPromptRun::factory()->create([
        'event_id' => $event->id,
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'selected_variation_id' => 'premium-album',
        'response_payload_json' => [
            'selected_variation' => [
                'id' => 'premium-album',
                'apply_scope' => 'all',
                'applied_at' => now()->subMinutes(5)->toIso8601String(),
            ],
        ],
    ]);

    $telemetry = $this->apiPost("/events/{$event->id}/gallery/telemetry", [
        'event' => 'vitals_sample',
        'viewport' => 'mobile',
        'item_count' => 36,
        'layout' => 'masonry',
        'density' => 'comfortable',
        'render_mode' => 'standard',
        'lcp_ms' => 1800,
        'inp_ms' => 120,
        'cls' => 0.04,
        'preview_latency_ms' => 820,
        'publish_latency_ms' => 1160,
    ]);

    $this->assertApiSuccess($telemetry);

    $boot = $this->apiGet("/events/{$event->id}/gallery/settings");

    $this->assertApiSuccess($boot);
    $boot->assertJsonPath('data.optimized_renderer_trigger.item_count', 500)
        ->assertJsonPath('data.optimized_renderer_trigger.estimated_rendered_height_px', 24000)
        ->assertJsonPath('data.settings.current_preset_origin.key', 'wedding-premium')
        ->assertJsonPath('data.operational_feedback.current_preset_origin.label', 'Wedding premium')
        ->assertJsonPath('data.operational_feedback.last_ai_application.variation_id', 'premium-album')
        ->assertJsonPath('data.operational_feedback.last_publish.version_number', 4)
        ->assertJsonPath('data.operational_feedback.last_restore.source_version_number', 2);

    $analyticsEvent = AnalyticsEvent::query()
        ->where('event_id', $event->id)
        ->where('event_name', 'gallery.builder_vitals_sample')
        ->first();

    expect($analyticsEvent)->not->toBeNull()
        ->and($analyticsEvent?->channel)->toBe('gallery_builder')
        ->and($analyticsEvent?->metadata_json['render_mode'] ?? null)->toBe('standard')
        ->and($analyticsEvent?->metadata_json['item_count'] ?? null)->toBe(36);
});
