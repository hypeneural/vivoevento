<?php

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use Carbon\Carbon;

it('returns event pipeline metrics with sla backlog and failure breakdown', function () {
    [, $organization] = $this->actingAsOwner();

    $base = Carbon::parse('2026-04-02 12:00:00');

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $inboundMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-pipeline-metrics-1',
        'message_type' => 'image',
        'status' => 'received',
        'received_at' => $base->copy()->subSeconds(15),
    ]);

    $publishedMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inboundMessage->id,
        'created_at' => $base,
        'published_at' => $base->copy()->addSeconds(30),
        'safety_status' => 'pass',
        'vlm_status' => 'completed',
        'face_index_status' => 'indexed',
    ]);

    $pendingMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'created_at' => $base->copy()->addSeconds(20),
        'moderation_status' => ModerationStatus::Pending->value,
        'publication_status' => PublicationStatus::Draft->value,
        'safety_status' => 'review',
        'vlm_status' => 'queued',
        'face_index_status' => 'queued',
    ]);

    $rejectedMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'created_at' => $base->copy()->addSeconds(40),
        'moderation_status' => ModerationStatus::Rejected->value,
        'publication_status' => PublicationStatus::Draft->value,
        'safety_status' => 'block',
        'vlm_status' => 'skipped',
        'face_index_status' => 'skipped',
    ]);

    MediaProcessingRun::query()->create([
        'event_media_id' => $publishedMedia->id,
        'run_type' => 'variants',
        'stage_key' => 'variants',
        'queue_name' => 'media-variants',
        'worker_ref' => 'worker-a',
        'status' => 'completed',
        'attempts' => 1,
        'started_at' => $base->copy()->addSecond(),
        'finished_at' => $base->copy()->addSeconds(5),
    ]);

    MediaProcessingRun::query()->create([
        'event_media_id' => $publishedMedia->id,
        'run_type' => 'face_index',
        'stage_key' => 'face_index',
        'queue_name' => 'face-index',
        'worker_ref' => 'worker-b',
        'status' => 'completed',
        'attempts' => 1,
        'started_at' => $base->copy()->addSeconds(10),
        'finished_at' => $base->copy()->addSeconds(50),
    ]);

    MediaProcessingRun::query()->create([
        'event_media_id' => $pendingMedia->id,
        'run_type' => 'variants',
        'stage_key' => 'variants',
        'queue_name' => 'media-audit',
        'worker_ref' => 'worker-a',
        'status' => 'completed',
        'attempts' => 1,
        'started_at' => $base->copy()->addSeconds(21),
        'finished_at' => $base->copy()->addSeconds(30),
    ]);

    MediaProcessingRun::query()->create([
        'event_media_id' => $pendingMedia->id,
        'run_type' => 'vlm',
        'stage_key' => 'vlm',
        'queue_name' => 'media-vlm',
        'worker_ref' => 'worker-c',
        'status' => 'failed',
        'attempts' => 2,
        'failure_class' => 'transient',
        'error_message' => 'timeout',
        'started_at' => $base->copy()->addSeconds(31),
        'finished_at' => $base->copy()->addSeconds(40),
    ]);

    MediaProcessingRun::query()->create([
        'event_media_id' => $rejectedMedia->id,
        'run_type' => 'face_index',
        'stage_key' => 'face_index',
        'queue_name' => 'face-index',
        'worker_ref' => 'worker-d',
        'status' => 'processing',
        'attempts' => 1,
        'started_at' => $base->copy()->addSeconds(45),
    ]);

    $response = $this->apiGet("/events/{$event->id}/media/pipeline-metrics");

    $this->assertApiSuccess($response);

    $payload = $response->json('data');

    $response
        ->assertJsonPath('data.summary.media_total', 3)
        ->assertJsonPath('data.summary.published_total', 1)
        ->assertJsonPath('data.summary.pending_total', 1)
        ->assertJsonPath('data.summary.rejected_total', 1)
        ->assertJsonPath('data.summary.blocked_total', 1)
        ->assertJsonPath('data.summary.review_total', 1)
        ->assertJsonPath('data.sla.upload_to_publish_seconds.count', 1)
        ->assertJsonPath('data.sla.inbound_to_publish_seconds.count', 1)
        ->assertJsonPath('data.sla.upload_to_first_update_seconds.count', 2)
        ->assertJsonPath('data.queues.backlog.0.queue_name', 'face-index')
        ->assertJsonPath('data.queues.backlog.0.processing_runs', 1)
        ->assertJsonPath('data.failures.0.stage_key', 'vlm')
        ->assertJsonPath('data.failures.0.failure_class', 'transient')
        ->assertJsonPath('data.failures.0.count', 1);

    expect((float) data_get($payload, 'sla.upload_to_publish_seconds.avg'))->toBe(30.0)
        ->and((float) data_get($payload, 'sla.inbound_to_publish_seconds.avg'))->toBe(45.0)
        ->and((float) data_get($payload, 'sla.upload_to_face_index_seconds.avg'))->toBe(50.0);
});
