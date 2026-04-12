<?php

use App\Modules\EventOperations\Models\EventOperationEvent;
use App\Modules\EventOperations\Models\EventOperationSnapshot;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\MediaProcessing\Events\MediaVariantsGenerated;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Events\WallDiagnosticsUpdated;
use App\Modules\Wall\Events\WallMediaPublished;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\WhatsApp\Models\WhatsAppMessageFeedback;

it('projects inbound media pipeline moderation gallery wall and feedback signals into append only operations rows', function () {
    $event = \App\Modules\Events\Models\Event::factory()->active()->manualModeration()->create([
        'title' => 'Casamento Ana e Bruno',
        'slug' => 'casamento-ana-bruno',
    ]);

    $wallSettings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $inbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'wamid-projection-001',
        'message_type' => 'image',
        'chat_external_id' => '5511999999999',
        'sender_external_id' => '5511999999999',
        'sender_name' => 'Ana',
        'body_text' => 'Momento especial',
        'from_me' => false,
        'normalized_payload_json' => [
            '_event_context' => [
                'intake_source' => 'whatsapp_group',
            ],
        ],
        'status' => 'received',
        'received_at' => now()->subSeconds(30),
    ]);

    $approvedMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inbound->id,
        'source_type' => 'whatsapp_group',
        'source_label' => 'WhatsApp',
        'caption' => 'Aprovada na galeria',
    ]);

    $rejectedMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'caption' => 'Rejeitada no fluxo',
        'safety_status' => 'block',
    ]);

    event(MediaVariantsGenerated::fromMedia($approvedMedia));
    event(MediaPublished::fromMedia($approvedMedia));
    event(MediaRejected::fromMedia($rejectedMedia));
    event(new WallMediaPublished($wallSettings->wall_code, [
        'id' => $approvedMedia->id,
        'type' => 'image',
        'url' => 'https://example.test/wall-approved.jpg',
        'caption' => 'Aprovada na galeria',
    ]));
    event(new WallDiagnosticsUpdated($event->id, [
        'health_status' => 'degraded',
        'online_players' => 1,
        'offline_players' => 1,
        'degraded_players' => 0,
        'error_count' => 2,
        'stale_count' => 1,
        'updated_at' => now()->toIso8601String(),
    ]));

    $feedback = WhatsAppMessageFeedback::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $approvedMedia->id,
        'inbound_message_id' => $inbound->id,
        'feedback_kind' => 'reaction',
        'feedback_phase' => 'published',
        'status' => 'pending',
    ]);

    $feedback->update([
        'status' => 'sent',
        'completed_at' => now(),
    ]);

    expect(EventOperationEvent::query()->where('event_id', $event->id)->count())->toBe(10)
        ->and(EventOperationEvent::query()->where('event_id', $event->id)->pluck('station_key')->all())
        ->toContain('intake', 'download', 'variants', 'human_review', 'safety', 'gallery', 'wall', 'feedback');

    $variantsEvent = EventOperationEvent::query()
        ->where('event_id', $event->id)
        ->where('event_key', 'media.variants.generated')
        ->first();

    $feedbackEvent = EventOperationEvent::query()
        ->where('event_id', $event->id)
        ->where('event_key', 'feedback.sent')
        ->first();

    $snapshot = EventOperationSnapshot::query()->where('event_id', $event->id)->first();

    expect($variantsEvent)->not->toBeNull()
        ->and($variantsEvent->dedupe_window_key)->not->toBeNull()
        ->and($feedbackEvent)->not->toBeNull()
        ->and($feedbackEvent->station_key)->toBe('feedback')
        ->and($snapshot)->not->toBeNull()
        ->and($snapshot->latest_event_sequence)->toBe(10)
        ->and($snapshot->timeline_cursor)->toBe('evt_000010')
        ->and(data_get($snapshot->snapshot_json, 'wall.offline_players'))->toBe(1)
        ->and(data_get($snapshot->snapshot_json, 'health.status'))->toBe('risk');
});
