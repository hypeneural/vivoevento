<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use App\Modules\MediaProcessing\Models\EventMediaVariant;

it('lists event media with a paginated frontend friendly schema', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);

    $message = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-evt-media',
        'message_type' => 'image',
        'sender_phone' => '5511999999999',
        'sender_name' => 'Maria',
        'status' => 'received',
        'received_at' => now(),
    ]);

    EventMedia::factory()->published()->count(2)->create([
        'event_id' => $event->id,
        'inbound_message_id' => $message->id,
        'source_type' => 'whatsapp',
    ]);

    $response = $this->apiGet("/events/{$event->id}/media?per_page=2");

    $this->assertApiPaginated($response);
    $response->assertJsonPath('data.0.channel', 'whatsapp')
        ->assertJsonPath('data.0.status', 'published')
        ->assertJsonPath('data.0.sender_name', 'Maria');
});

it('shows detailed media payload with preview and original asset urls', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'original_filename' => 'clip.mp4',
        'mime_type' => 'video/mp4',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'gallery',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/gallery/clip.mp4',
        'mime_type' => 'video/mp4',
        'width' => 1280,
        'height' => 720,
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/thumb/clip.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 640,
        'height' => 360,
    ]);

    $response = $this->apiGet("/media/{$media->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.id', $media->id)
        ->assertJsonPath('data.media_type', 'video')
        ->assertJsonPath('data.mime_type', 'video/mp4')
        ->assertJsonPath('data.preview_url', rtrim((string) config('app.url'), '/')."/storage/events/{$event->id}/gallery/clip.mp4")
        ->assertJsonPath('data.original_url', rtrim((string) config('app.url'), '/')."/storage/events/{$event->id}/originals/clip.mp4")
        ->assertJsonPath('data.variants.0.variant_key', 'gallery');
});

it('prefers fast preview assets and exposes enriched processing runs in the detail payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'original_disk' => 'public',
        'original_path' => "events/{$event->id}/originals/foto.jpg",
        'original_filename' => 'foto.jpg',
        'mime_type' => 'image/jpeg',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'fast_preview',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$media->id}/fast_preview.webp",
        'mime_type' => 'image/webp',
        'width' => 512,
        'height' => 341,
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'gallery',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$media->id}/gallery.webp",
        'mime_type' => 'image/webp',
        'width' => 1600,
        'height' => 1067,
    ]);

    MediaProcessingRun::query()->create([
        'event_media_id' => $media->id,
        'run_type' => 'variants',
        'stage_key' => 'variants',
        'provider_key' => 'intervention-image',
        'provider_version' => 'v4',
        'model_key' => 'intervention-image-v4',
        'model_snapshot' => 'intervention-image-v4',
        'input_ref' => "events/{$event->id}/variants/{$media->id}/fast_preview.webp",
        'decision_key' => 'generated',
        'queue_name' => 'media-fast',
        'worker_ref' => 'worker-a',
        'result_json' => ['variant_keys' => ['fast_preview', 'gallery']],
        'metrics_json' => ['generated_count' => 2],
        'cost_units' => 0.1250,
        'idempotency_key' => "variants:{$media->id}",
        'status' => 'completed',
        'attempts' => 1,
        'failure_class' => null,
        'started_at' => now()->subSecond(),
        'finished_at' => now(),
    ]);

    $response = $this->apiGet("/media/{$media->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.preview_url', rtrim((string) config('app.url'), '/')."/storage/events/{$event->id}/variants/{$media->id}/fast_preview.webp")
        ->assertJsonPath('data.processing_runs.0.queue_name', 'media-fast')
        ->assertJsonPath('data.processing_runs.0.worker_ref', 'worker-a')
        ->assertJsonPath('data.processing_runs.0.provider_version', 'v4')
        ->assertJsonPath('data.processing_runs.0.model_snapshot', 'intervention-image-v4')
        ->assertJsonPath('data.processing_runs.0.cost_units', 0.125)
        ->assertJsonPath('data.processing_runs.0.failure_class', null);
});

it('shows the latest safety and vlm evaluations in the media detail payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'moderation_status' => 'pending',
        'safety_status' => 'pass',
        'vlm_status' => 'completed',
        'caption' => 'Entrada especial na festa.',
    ]);

    EventMediaSafetyEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'review',
        'review_required' => true,
        'blocked' => false,
        'reason_codes_json' => ['violence'],
        'category_scores_json' => ['nudity' => 0.02, 'violence' => 0.61],
        'completed_at' => now()->subMinute(),
    ]);

    $latestSafety = EventMediaSafetyEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'pass',
        'review_required' => false,
        'blocked' => false,
        'reason_codes_json' => [],
        'category_scores_json' => ['nudity' => 0.01, 'violence' => 0.03],
        'provider_categories_json' => ['violence' => false, 'sexual' => false],
        'provider_category_scores_json' => ['violence' => 0.03, 'sexual' => 0.01],
        'provider_category_input_types_json' => ['violence' => ['image']],
        'normalized_provider_json' => [
            'categories' => ['violence' => false, 'sexual' => false],
            'category_scores' => ['violence' => 0.03, 'sexual' => 0.01],
            'category_applied_input_types' => ['violence' => ['image']],
            'input_path_used' => 'image_url',
        ],
        'completed_at' => now(),
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'review',
        'reason' => 'Imagem precisa de revisao.',
        'short_caption' => 'Legenda antiga.',
        'tags_json' => ['fila'],
        'completed_at' => now()->subMinute(),
    ]);

    $latestVlm = EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'approve',
        'reason' => 'Imagem compativel com o evento.',
        'short_caption' => 'Entrada especial na festa.',
        'tags_json' => ['festa', 'retrato'],
        'completed_at' => now(),
    ]);

    $response = $this->apiGet("/media/{$media->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.latest_safety_evaluation.id', $latestSafety->id)
        ->assertJsonPath('data.latest_safety_evaluation.decision', 'pass')
        ->assertJsonPath('data.latest_safety_evaluation.category_scores.violence', 0.03)
        ->assertJsonPath('data.latest_safety_evaluation.provider_category_scores.violence', 0.03)
        ->assertJsonPath('data.latest_safety_evaluation.provider_category_input_types.violence.0', 'image')
        ->assertJsonPath('data.latest_safety_evaluation.normalized_provider.input_path_used', 'image_url')
        ->assertJsonPath('data.caption_source_hint', 'vlm')
        ->assertJsonPath('data.latest_vlm_evaluation.id', $latestVlm->id)
        ->assertJsonPath('data.latest_vlm_evaluation.decision', 'approve')
        ->assertJsonPath('data.latest_vlm_evaluation.reason', 'Imagem compativel com o evento.')
        ->assertJsonPath('data.latest_vlm_evaluation.short_caption', 'Entrada especial na festa.')
        ->assertJsonPath('data.latest_vlm_evaluation.tags.0', 'festa');
});

it('marks caption source as human when the stored caption differs from the latest vlm short caption', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'caption' => 'Legenda humana preservada.',
        'vlm_status' => 'completed',
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'approve',
        'reason' => 'Imagem compativel com o evento.',
        'short_caption' => 'Legenda sugerida pela IA.',
        'tags_json' => ['festa'],
        'completed_at' => now(),
    ]);

    $response = $this->apiGet("/media/{$media->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.caption_source_hint', 'human');
});
