<?php

use App\Modules\EventOperations\Support\EventOperationsAttentionPriority;
use App\Modules\EventOperations\Support\EventOperationsEventMapper;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\MediaProcessing\Events\MediaVariantsGenerated;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Events\WallDiagnosticsUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('maps inbound intake to the intake station with low priority', function () {
    $event = \App\Modules\Events\Models\Event::factory()->active()->create();

    $message = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'wamid-inbound-001',
        'message_type' => 'image',
        'chat_external_id' => '5511999999999',
        'sender_external_id' => '5511999999999',
        'sender_name' => 'Ana',
        'body_text' => 'Primeira foto',
        'from_me' => false,
        'normalized_payload_json' => [
            '_event_context' => [
                'intake_source' => 'whatsapp_group',
            ],
        ],
        'status' => 'received',
        'received_at' => now(),
    ]);

    $mapped = app(EventOperationsEventMapper::class)->fromInboundMessage($message);

    expect($mapped)->not->toBeNull()
        ->and($mapped['station_key'])->toBe('intake')
        ->and($mapped['event_key'])->toBe('media.card.arrived')
        ->and($mapped['animation_hint'])->toBe('intake_pulse')
        ->and($mapped['render_group'])->toBe('intake')
        ->and($mapped['priority'])->toBe(EventOperationsAttentionPriority::TIMELINE_COALESCIBLE);
});

it('maps media decisions to the moderation stations with the proper emphasis', function () {
    $event = \App\Modules\Events\Models\Event::factory()->active()->manualModeration()->create();

    $approvedMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'caption' => 'Aprovada',
    ]);

    $rejectedMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'caption' => 'Rejeitada',
        'safety_status' => 'block',
    ]);

    $mapper = app(EventOperationsEventMapper::class);

    $approved = $mapper->fromMediaPublishedToModeration(MediaPublished::fromMedia($approvedMedia));
    $rejected = $mapper->fromMediaRejected(MediaRejected::fromMedia($rejectedMedia));

    expect($approved)->not->toBeNull()
        ->and($approved['station_key'])->toBe('human_review')
        ->and($approved['event_key'])->toBe('media.moderation.approved')
        ->and($approved['priority'])->toBe(EventOperationsAttentionPriority::TIMELINE_COALESCIBLE)
        ->and($rejected)->not->toBeNull()
        ->and($rejected['station_key'])->toBe('safety')
        ->and($rejected['event_key'])->toBe('media.moderation.rejected')
        ->and($rejected['severity'])->toBe('warning')
        ->and($rejected['priority'])->toBe(EventOperationsAttentionPriority::OPERATIONAL_NORMAL);
});

it('maps wall diagnostics into a critical wall health signal when players are offline', function () {
    $event = \App\Modules\Events\Models\Event::factory()->active()->create();

    $mapped = app(EventOperationsEventMapper::class)->fromWallDiagnosticsUpdated(
        new WallDiagnosticsUpdated($event->id, [
            'health_status' => 'degraded',
            'online_players' => 1,
            'offline_players' => 1,
            'degraded_players' => 0,
            'error_count' => 2,
            'stale_count' => 1,
            'updated_at' => now()->toIso8601String(),
        ]),
    );

    expect($mapped)->not->toBeNull()
        ->and($mapped['station_key'])->toBe('wall')
        ->and($mapped['event_key'])->toBe('wall.health.changed')
        ->and($mapped['severity'])->toBe('critical')
        ->and($mapped['urgency'])->toBe('critical')
        ->and($mapped['animation_hint'])->toBe('wall_alert')
        ->and($mapped['priority'])->toBe(EventOperationsAttentionPriority::CRITICAL_IMMEDIATE);
});
